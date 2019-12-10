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
      SELECT *, gp_orders.invoice_number, gp_rxs_grouped.drug_generic, gp_rxs_grouped.drug_brand, gp_rxs_grouped.drug_name
      FROM
        gp_orders
      JOIN gp_patients ON
        gp_patients.patient_id_cp = gp_orders.patient_id_cp
      LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
        gp_rxs_grouped.patient_id_cp = gp_orders.patient_id_cp
      LEFT JOIN gp_order_items ON
        gp_order_items.invoice_number = $order[invoice_number] AND rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%') -- In case the rx is added in a different orders
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
        $order[$i]['drug'] = $item['drug_generic'] ?: $item['drug_name'];
        $order[$i]['item_message_text'] = $item['rx_number'] ? ($item['item_message_text'] ?: '') : message_text(get_days_dispensed($item)[1], $item); //Get rid of NULL. //if not syncing to order lets provide a reason why we are not filling
        $order[$i]['days_dispensed'] = $item['days_dispensed_actual'] ?: $item['days_dispensed_default'];
        $order[$i]['qty_dispensed'] = (float) ($item['qty_dispensed_actual'] ?: $item['qty_dispensed_default']); //cast to float to get rid of .000 decimal
        $order[$i]['refills_total'] = (float) ($item['refills_total_actual'] ?: $item['refills_total_default']);
        $order[$i]['price_dispensed'] = (float) ($item['price_dispensed_actual'] ?: ($item['price_dispensed_default'] ?: 0));
      }

      usort($order, 'sort_order_by_day');
    }

    log_info('get_full_order every item with 0 days should have message', get_defined_vars());
    return $order;
  }

  function sort_order_by_day($a, $b) {
    if ($b['days_dispensed'] > 0 AND $a['days_dispensed'] == 0) return 1;
    if ($a['days_dispensed'] > 0 AND $b['days_dispensed'] == 0) return -1;
    return strcmp($a['item_message_text'].$a['drug'], $b['item_message_text'].$b['drug']);
  }

  function sync_to_order($order, $mysql) {

    $order = get_full_order($order, $mysql);

    foreach($order as $item) {

      if ( ! isset($item['invoice_number'])) {
        log_error('ERROR sync_to_order', get_defined_vars());
        continue;
      }

      if ($item['invoice_number']) continue; //Item is already in the order

      $days_to_refill = (strtotime($item['refill_date_next']) - strtotime($item['order_date_added']))/60/60/24;

      if ($days_to_refill < 15)
        log_info('TODO: Add this item to the Order', get_defined_vars());
    }
  }

  //Group all drugs by their next fill date and get the most popular date
  function get_sync_to_date($order) {

    $sync_dates = [];
    foreach ($order as $item) {
      if (isset($sync_dates[$item['refill_date_next']]))
        $sync_dates[$item['refill_date_next']][] = $item['rx_number'];
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

      if ( ! $item['days_dispensed']) continue; //Don't add them to order if they are already in it

      $days_extra  = (strtotime($target_date) - strtotime($item['refill_date_next']))/60/60/24;
      $days_synced = $item['days_dispensed'] + round($days_extra/15)*15;

      if ($days_synced >= 15 AND $days_synced <= 120 AND $days_synced != 90) { //Limits to the amounts by which we are willing sync

        $order[$i]['refill_target_date'] = $target_date;
        $order[$i]['days_dispensed']     = $days_synced;
        $order[$i]['qty_dispensed']      = $days_synced*$item['sig_qty_per_day'];
        $order[$i]['price_dispensed']    = ceil($item['price_dispensed'] * $days_synced / $item['days_dispensed']); //Might be null

        $sql = "
          UPDATE
            gp_order_items
          SET
            item_message_key        = '".RX_MESSAGE['NO ACTION SYNC TO DATE']['EN']."',
            refill_target_date      = '$target_date',
            refill_target_days      = ".($days_synced - $item['days_dispensed']).",
            refill_target_rxs       = '$target_rxs',
            days_dispensed_default  = $days_synced,
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
      "MIN_DAYS" => INF
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

      $price = $item['price_dispensed'] ? ', $'.((float) $item['price_dispensed']).$msg.' for '.$days.' days' : '';

      $groups['ALL'][] = $item;
      $groups[$fill.$action][] = $item['drug'].$msg;

      $sql = "
        UPDATE
          gp_order_items
        SET
         `group` = CASE WHEN `group` is NULL THEN '$fill$action' ELSE concat('$fill$action < ', `group`) END
        WHERE
          invoice_number = $item[invoice_number] AND
          rx_number = $item[rx_number]
      ";

      $mysql->run($sql);

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

  function send_created_order_communications($order, $mysql) {

    $groups = group_drugs($order, $mysql);

    if ( ! $order[0]['pharmacy_name']) //Use Pharmacy name rather than $New to keep us from repinging folks if the row has been readded
      needs_form_notice($groups);

    else if ( ! $groups['COUNT_NOFILL'] AND ! $groups['COUNT_FILLED'])
      no_rx_notice($groups);

    else if ( ! $groups['COUNT_FILLED'])
      order_hold_notice($groups);

    //['Not Specified', 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
    else if ($order[0]['order_source'] == 'Webform Transfer' OR $order[0]['order_source'] == 'Transfer /w Note')
      transfer_requested_notice($groups);

    else
      order_created_notice($groups);

    if ($order[0]['order_source']['tracking_number'])
      log_error('Error? Order with tracking number was deleted', get_defined_vars());
  }

  function send_deleted_order_communications($order) {

    //TODO We need something here!
    order_canceled_notice($order);
    log_info('Order was deleted', get_defined_vars());
  }

  function send_updated_order_communications($order, $mysql, $updated) {

    $groups = group_drugs($order, $mysql);

    if ($order[0]['tracking_number']) {
      order_shipped_notice($groups);
      confirm_shipment_notice($groups);
      refill_reminder_notice($groups);
      unpend_order($order);

      if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
        autopay_reminder_notice($groups);
    }

    else if ($order[0]['order_status'] == 'Dispensed')
      order_dispensed_notice($groups);

    else {
      order_updated_notice($groups);
      log_info('order_updated_notice', get_defined_vars());
    }
  }

  //If just added to CP Order we need to
  //  - Find out any other rxs need to be added
  //  - Update invoice
  //  - Update wc order count/total
  foreach($changes['created'] as $created) {

    sync_to_order($created, $mysql);

    $order = get_full_order($created, $mysql);

    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    $update = get_payment($order);
    $order  = set_payment($order, $update, $mysql);

    export_gd_update_invoice($order);

    export_wc_update_order($order);

    send_created_order_communications($order, $mysql);


    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

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
    log_info("update_orders", get_defined_vars());

    $order = get_full_order($updated, $mysql);
    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?

    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    $update = get_payment($order);
    $order  = set_payment($order, $update, $mysql);

    export_gd_update_invoice($order);

    export_gd_publish_invoices($order);

    export_wc_update_order($order);

    send_updated_order_communications($order, $mysql, $updated);

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
