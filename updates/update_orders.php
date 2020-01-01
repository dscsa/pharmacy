<?php

require_once 'changes/changes_to_orders.php';
require_once 'helpers/helper_payment.php';
require_once 'helpers/helper_syncing.php';
require_once 'helpers/helper_communications.php';
require_once 'exports/export_wc_orders.php';

function update_orders() {

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
        $order[$i]['drug'] = $item['drug_name'] ?: $item['drug_generic'];
        $order[$i]['days_dispensed'] = $item['days_dispensed_actual'] ?: $item['days_dispensed_default'];

        if ( ! $item['item_date_added']) { //if not syncing to order lets provide a reason why we are not filling
          $message = get_days_default($item)[1];
          $order[$i]['item_message_key']  = array_search($message, RX_MESSAGE);
          $order[$i]['item_message_text'] = message_text($message, $item);
        }

        $deduct_refill = $order[$i]['days_dispensed'] ? 1 : 0; //We want invoice to show refills after they are dispensed assuming we dispense items currently in order

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

    if ($order[0]['order_date_shipped']) {
      log_notice("Shipped Order Being Readded", get_defined_vars());
      continue;
    }

    //1) Add Drugs to Guardian that should be in the order
    //2) Remove drug from guardian that should not be in the order
    //3) Create a fax out transfer for anything removed that is not offered
    $items_to_sync = sync_to_order($order);
    if ($items_to_sync) {
      log_info('sync_to_order: created', get_defined_vars());
      $mysql->run('DELETE gp_orders FROM gp_orders WHERE invoice_number = '.$order[0]['invoice_number']);
      continue; //DON'T CREATE THE ORDER UNTIL THESE ITEMS ARE SYNCED TO AVOID CONFLICTING COMMUNICATIONS!
    }

    //Needs to be called before "$groups" is set
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    $groups = group_drugs($order, $mysql);

    if ( ! $groups['COUNT_FILLED'] AND $groups['ALL'][0]['item_message_key'] != 'ACTION NEEDS FORM') {
      log_notice("Created Order But Not Filling Any?", get_defined_vars());
      continue;
    }

    helper_update_payment($order, $mysql);
    export_wc_update_order_metadata($order);
    export_wc_update_order_shipping($order);

    send_created_order_communications($groups);

    log_notice("Created Order", get_defined_vars());

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

    export_v2_unpend_order([$deleted]);

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

    //Remove only (e.g. new surescript comes in), let's not add more drugs to their order since communication already went out
    $items_to_sync = sync_to_order($order, $updated);
    if ($items_to_sync) {
      log_notice('sync_to_order: updated', get_defined_vars());
    }

    $stage_change = $updated['order_stage'] != $updated['old_order_stage'];

    //Needs to be called before "$groups" is set
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    $groups = group_drugs($order, $mysql);

    if ($stage_change AND $updated['order_date_shipped']) {
      export_wc_update_order_metadata($order);
      export_wc_update_order_shipping($order);
      send_shipped_order_communications($groups);
      log_notice("Updated Order Shipped", get_defined_vars());
      continue;
    }

    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    if ($stage_change AND $updated['order_date_dispensed']) {
      helper_update_payment($order, $mysql);
      export_wc_update_order_metadata($order);
      export_wc_update_order_shipping($order);
      send_dispensed_order_communications($groups);
      //log_notice("Updated Order Dispensed", get_defined_vars());
      continue;
    }

    if ($stage_change) {
      log_info("Updated Order Stage Change", get_defined_vars());
      continue;
    }

    //Usually count_items changed
    helper_update_payment($order, $mysql);

    send_updated_order_communications($groups);

    $updated['count_items'] == $updated['old_count_items']
      ? log_notice("Updated Order NO Stage Change", get_defined_vars())
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
