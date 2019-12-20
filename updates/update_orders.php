<?php

require_once 'changes/changes_to_orders.php';
require_once 'exports/export_gd_orders.php';
require_once 'exports/export_wc_orders.php';
require_once 'exports/export_gd_comm_calendar.php';

function update_orders() {

  watch_invoices();
  $changes = changes_to_orders("gp_orders_cp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_orders: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();

  //order created -> add any additional rxs to order -> import order items -> sync all drugs in order

  function get_full_order($order, $mysql) {

    //gp_orders.invoice_number and other fields at end because otherwise potentially null gp_order_items.invoice_number will override gp_orders.invoice_number
    $sql = "
      SELECT
        *,
        gp_orders.invoice_number,
        gp_rxs_grouped.* -- Need to put this first based on how we are joining, but make sure these grouped fields overwrite their single equivalents
      FROM
        gp_orders
      JOIN gp_patients ON
        gp_patients.patient_id_cp = gp_orders.patient_id_cp
      LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
        gp_rxs_grouped.patient_id_cp = gp_orders.patient_id_cp
      LEFT JOIN gp_order_items ON
        gp_order_items.invoice_number = gp_orders.invoice_number AND rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%') -- In case the rx is added in a different orders
      LEFT JOIN gp_rxs_single ON -- Needed to know qty_left for sync-to-date
        gp_order_items.rx_number = gp_rxs_single.rx_number
      LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
        gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic -- this is for the helper_days_dispensed msgs for unordered drugs
      WHERE
        gp_orders.invoice_number = $order[invoice_number]
    ";

    $order = $mysql->run($sql)[0];

    if ( ! $order OR ! $order[0]['invoice_number']) {
      log_error('ERROR! get_full_order: no invoice number', get_defined_vars());
    } else {
      //Consolidate default and actual suffixes to avoid conditional overload in the invoice template and redundant code within communications
      foreach($order as $i => $item) {

        $deduct_refill = $order[$i]['days_dispensed'] ? 1 : 0; //We want invoice to show refills after they are dispensed assuming we dispense items currently in order

        $order[$i]['drug'] = $item['drug_name'] ?: $item['drug_generic'];
        $order[$i]['item_message_text'] = $item['rx_number'] ? ($item['item_message_text'] ?: '') : message_text(get_days_default($item)[1], $item); //Get rid of NULL. //if not syncing to order lets provide a reason why we are not filling
        $order[$i]['days_dispensed'] = $item['days_dispensed_actual'] ?: $item['days_dispensed_default'];
        $order[$i]['qty_dispensed'] = (float) ($item['qty_dispensed_actual'] ?: $item['qty_dispensed_default']); //cast to float to get rid of .000 decimal
        $order[$i]['refills_total'] = (float) ($item['refills_total_actual'] ?: $item['refills_total_default'] - $deduct_refill);
        $order[$i]['price_dispensed'] = (float) ($item['price_dispensed_actual'] ?: ($item['price_dispensed_default'] ?: 0));
      }

      usort($order, 'sort_order_by_day');
    }

    //log_info('get_full_order', get_defined_vars());

    return $order;
  }

  function sort_order_by_day($a, $b) {
    if ($b['days_dispensed'] > 0 AND $a['days_dispensed'] == 0) return 1;
    if ($a['days_dispensed'] > 0 AND $b['days_dispensed'] == 0) return -1;
    return strcmp($a['item_message_text'].$a['drug'], $b['item_message_text'].$b['drug']);
  }

  function sync_to_order($order) {

    foreach($order as $item) {

      if ( ! isset($item['invoice_number'])) {
        log_error('ERROR sync_to_order', get_defined_vars());
        continue;
      }

      if ($item['item_date_added']) continue; //Item is already in the order

      if (sync_to_order_past_due($item)) {
        log_error("PAST DUE AND SYNC TO ORDER", get_defined_vars());
        return export_cp_add_item($item, "sync_to_order: PAST DUE AND SYNC TO ORDER");
      }

      if (sync_to_order_due_soon($item)) {
        log_error("DUE SOON AND SYNC TO ORDER", get_defined_vars());
        return export_cp_add_item($item, "sync_to_order: DUE SOON AND SYNC TO ORDER");
      }
    }
  }

  //Group all drugs by their next fill date and get the most popular date
  function get_sync_to_date($order) {

    $sync_dates = [];
    foreach ($order as $item) {
      if (isset($sync_dates[$item['refill_date_next']]))
        $sync_dates[$item['refill_date_next']][] = $item['best_rx_number']; //rx_number only set if in the order?
      else
        $sync_dates[$item['refill_date_next']] = [];
    }

    $target_date = null;
    $target_rxs  = [];

    foreach($sync_dates as $date => $rx_numbers) {

      $count = count($rx_numbers);
      $target_count = count($target_rxs);

      if ($count > $target_count) {
        $target_date = $date;
        $target_rxs  = $rx_numbers;
      }
      else if ($count == $target_count AND $date > $target_date) { //In case of tie, longest date wins
        $target_date = $date;
        $target_rxs  = $rx_numbers;
      }
    }

    return [$target_date, ','.implode(',', $target_rxs).','];
  }

  //Sync any drug that has days to the new refill date
  function set_sync_to_date($order, $target_date, $target_rxs, $mysql) {

    foreach($order as $i => $item) {

      $old_days_default = $item['days_dispensed_default'];

      //TODO Skip syncing if the drug is OUT OF STOCK (or less than 500 qty?)
      if ( ! $old_days_default OR $item['days_dispensed_actual'] OR $item['item_message_key'] == 'NO ACTION LOW STOCK') continue; //Don't add them to order if they are no already in it OR if already dispensed

      $time_refill = $item['refill_date_next'] ? strtotime($item['refill_date_next']) : time(); //refill_date_next is sometimes null
      $days_extra  = (strtotime($target_date) - $time_refill)/60/60/24;
      $days_synced = $old_days_default + round($days_extra/15)*15;

      $new_days_default = days_default($item, $days_synced);

      if ($new_days_default >= 15 AND $new_days_default <= 120 AND $new_days_default != $old_days_default) { //Limits to the amounts by which we are willing sync

        if ($new_days_default <= 30) {
          $new_days_default += 90;
          log_error('debug set_sync_to_date: extra time', get_defined_vars());
        } else {
          log_error('debug set_sync_to_date: std time', get_defined_vars());
        }

        $order[$i]['refill_target_date'] = $target_date;
        $order[$i]['days_dispensed']     = $new_days_default;
        $order[$i]['qty_dispensed']      = $new_days_default*$item['sig_qty_per_day'];
        $order[$i]['price_dispensed']    = ceil($item['price_dispensed'] * $new_days_default / $old_days_default); //Might be null

        $sql = "
          UPDATE
            gp_order_items
          SET
            item_message_key        = 'NO ACTION SYNC TO DATE',
            item_message_text       = '".RX_MESSAGE['NO ACTION SYNC TO DATE'][$item['language']]."',
            refill_target_date      = '$target_date',
            refill_target_days      = ".($new_days_default - $old_days_default).",
            refill_target_rxs       = '$target_rxs',
            days_dispensed_default  = $new_days_default,
            qty_dispensed_default   = ".$order[$i]['qty_dispensed'].",
            price_dispensed_default = ".$order[$i]['price_dispensed']."
          WHERE
            rx_number = $item[rx_number]
        ";

        $mysql->run($sql);
      }

      export_v2_add_pended($order[$i]); //Days should be finalized now
    }

    return $order;
  }

  function get_payment($order) {

    $update = [];

    $update['payment_total'] = 0;

    foreach($order as $i => $item)
      $update['payment_total'] += $item['price_dispensed'];

    //Defaults
    $update['payment_fee'] = $order[0]['refills_used'] ? $update['payment_total'] : PAYMENT_TOTAL_NEW_PATIENT;
    $update['payment_due'] = $update['payment_fee'];
    $update['payment_date_autopay'] = 'NULL';

    if ($order[0]['payment_method'] == PAYMENT_METHOD['COUPON']) {
      $update['payment_fee'] = $update['payment_total'];
      $update['payment_due'] = 0;
    }
    else if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY']) {
      $start = date('m/01', strtotime('+ 1 month'));
      $stop  = date('m/07/y', strtotime('+ 1 month'));

      $update['payment_date_autopay'] = "'$start - $stop'";
      $update['payment_due'] = 0;
    }

    return $update;
  }

  function set_payment($order, $update, $mysql) {

    $sql = "
      UPDATE
        gp_orders
      SET
        payment_total = $update[payment_total],
        payment_fee   = $update[payment_fee],
        payment_due   = $update[payment_due],
        payment_date_autopay = $update[payment_date_autopay]
      WHERE
        invoice_number = {$order[0]['invoice_number']}
    ";

    $mysql->run($sql);

    foreach($order as $i => $item)
      $order[$i] = $update + $item;

    return $order;
  }

  function unpend_order($order) {
    foreach($order as $item) {
      export_v2_remove_pended($item);
    }
  }

  //All Communication should group drugs into 4 Categories based on ACTION/NOACTION and FILL/NOFILL
  //1) FILLING NO ACTION
  //2) FILLING ACTION
  //3) NOT FILLING ACTION
  //4) NOT FILLING NO ACTION
  function group_drugs($order, $mysql) {

    $groups = [
      "ALL" => [],
      "FILLED_ACTION" => [],
      "FILLED_NOACTION" => [],
      "NOFILL_ACTION" => [],
      "NOFILL_NOACTION" => [],
      "FILLED" => [],
      "FILLED_WITH_PRICES" => [],
      "NO_REFILLS" => [],
      "NO_AUTOFILL" => [],
      "MIN_DAYS" => 366 //Max Days of a Script
    ];

    foreach ($order as $item) {

      if ( ! $item['drug_name']) continue; //Might be an empty order

      $days = $item['days_dispensed'];
      $fill = $days ? 'FILLED_' : 'NOFILL_';
      $msg  = $item['item_message_text'] ? ' '.$item['item_message_text'] : '';

      if (strpos($item['item_message_key'], 'NO ACTION') !== false)
        $action = 'NOACTION';
      else if (strpos($item['item_message_key'], 'ACTION') !== false)
        $action = 'ACTION';
      else
        $action = 'NOACTION';

      $price = $item['price_dispensed'] ? ', $'.((float) $item['price_dispensed']).' for '.$days.' days' : '';

      $groups['ALL'][] = $item;
      $groups[$fill.$action][] = $item['drug'].$msg;

      if ($item['rx_number']) { //Will be null drug is NOT in the order. "Group" is keyword so must have ``
        $sql = "
          UPDATE
            gp_order_items
          SET
           `group` = CASE WHEN `group` is NULL THEN '$fill$action' ELSE concat('$fill$action < ', `group`) END
          WHERE
            invoice_number = $item[invoice_number] AND
            rx_number = $item[rx_number] AND
            `group` != '$fill$action'
        ";

        $mysql->run($sql);
      }

      if ($days) {//This is handy because it is not appended with a message like the others
        $groups['FILLED'][] = $item['drug'];
        $groups['FILLED_WITH_PRICES'][] = $item['drug'].$price;
      }

      if ( ! $item['refills_total'])
        $groups['NO_REFILLS'][] = $item['drug'].$msg;

      if ($days AND ! $item['rx_autofill'])
        $groups['NO_AUTOFILL'][] = $item['drug'].$msg;

      if ( ! $item['refills_total'] AND $days AND $days < $groups['MIN_DAYS'])
        $groups['MIN_DAYS'] = $days;

      $groups['MANUALLY_ADDED'] = $item['item_added_by'] == 'MANUAL' OR $item['item_added_by'] == 'WEBFORM';
    }

    $groups['COUNT_FILLED'] = count($groups['FILLED_ACTION']) + count($groups['FILLED_NOACTION']);
    $groups['COUNT_NOFILL'] = count($groups['NOFILL_ACTION']) + count($groups['NOFILL_NOACTION']);

    $sql = "
      UPDATE
        gp_orders
      SET
        count_filled = '$groups[COUNT_FILLED]',
        count_nofill = '$groups[COUNT_NOFILL]'
      WHERE
        invoice_number = {$order[0]['invoice_number']}
    ";

    $mysql->run($sql);

    log_info('GROUP_DRUGS', get_defined_vars());

    return $groups;
  }

  function update_payment($order, $mysql) {
    $update = get_payment($order);
    $order  = set_payment($order, $update, $mysql);

    export_gd_update_invoice($order);
    export_gd_publish_invoices($order);

    export_wc_update_order($order);
  }

  function send_created_order_communications($groups) {

    if ( ! $groups['ALL'][0]['pharmacy_name']) //Use Pharmacy name rather than $New to keep us from repinging folks if the row has been readded
      needs_form_notice($groups);

    else if ( ! $groups['COUNT_NOFILL'] AND ! $groups['COUNT_FILLED'])
      no_rx_notice($groups);

    else if ( ! $groups['COUNT_FILLED'])
      order_hold_notice($groups);

    //['Not Specified', 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
    else if ($groups['ALL'][0]['order_source'] == 'Webform Transfer' OR $groups['ALL'][0]['order_source'] == 'Transfer /w Note')
      transfer_requested_notice($groups);

    else
      order_created_notice($groups);
  }

  function send_deleted_order_communications($order) {

    //TODO We need something here!
    order_canceled_notice($order);
    log_info('Order was deleted', get_defined_vars());
  }

  function send_shipped_order_communications($groups) {

    order_shipped_notice($groups);
    confirm_shipment_notice($groups);
    refill_reminder_notice($groups);
    unpend_order($groups['ALL']);

    if ($groups['ALL'][0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
      autopay_reminder_notice($groups);
  }

  function send_dispensed_order_communications($groups) {
    order_dispensed_notice($groups);
  }


  function send_updated_order_communications($groups) {
    order_updated_notice($groups);
    log_info('order_updated_notice', get_defined_vars());
  }

  //If just added to CP Order we need to
  //  - Find out any other rxs need to be added
  //  - Update invoice
  //  - Update wc order count/total
  foreach($changes['created'] as $created) {

    $order = get_full_order($created, $mysql);

    if ( ! $order) {
      log_error("Created Order Missing", get_defined_vars());
      continue;
    }

    sync_to_order($order);

    $groups = group_drugs($order, $mysql);

    if ( ! $groups['COUNT_FILLED'] AND $groups['ALL'][0]['item_message_key'] != 'ACTION NEEDS FORM') {
      log_error("Created Order But Not Filling Any?", get_defined_vars());
      continue;
    }

    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    update_payment($order, $mysql);

    send_created_order_communications($groups);

    log_info("Created Order", get_defined_vars());

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    if ($deleted['tracking_number'])
      log_error('Error? Order with tracking number was deleted', get_defined_vars());

    export_gd_delete_invoice([$deleted]);

    export_wc_delete_order([$deleted]);

    unpend_order([$deleted]);

    send_deleted_order_communications([$deleted]);


    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    $changed_fields = changed_fields($updated);

    $order = get_full_order($updated, $mysql);

    if ( ! $order) {
      log_error("Updated Order Missing", get_defined_vars());
      continue;
    }

    $stage_change = $updated['order_stage'] != $updated['old_order_stage'];

    $groups = group_drugs($order, $mysql);

    if ($stage_change AND $updated['order_date_shipped']) {
      send_shipped_order_communications($groups);
      log_error("Updated Order Shipped", get_defined_vars());
      continue;
    }

    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    if ($stage_change AND $updated['order_date_dispensed']) {
      update_payment($order, $mysql);
      send_dispensed_order_communications($groups);
      //log_error("Updated Order Dispensed", get_defined_vars());
      continue;
    }

    if ($stage_change) {
      log_info("Updated Order Stage Change", get_defined_vars());
      continue;
    }

    //Usually count_items changed
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    //Usually count_items changed
    update_payment($order, $mysql);

    send_updated_order_communications($groups);

    $updated['count_items'] == $updated['old_count_items']
      ? log_error("Updated Order NO Stage Change", get_defined_vars())
      : log_info("Updated Order Item Count Change", get_defined_vars());

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration

  }

  //TODO Differentiate between actual order that are to be sent out and
  // - Ones that were faxed/called in but not due yet
  // - Ones that were surescripted in but not due yet  [order_status] => Surescripts Fill
  // [order_status] => Surescripts Authorization Approved

  //TODO Upsert WooCommerce Order Status, Order Tracking

  //TODO Upsert Salseforce Order Status, Order Tracking

  //TODO Remove Delete Orders

  }
