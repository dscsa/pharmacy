<?php

require_once 'changes/changes_to_orders_cp.php';
require_once 'helpers/helper_full_order.php';
require_once 'helpers/helper_payment.php';
require_once 'helpers/helper_syncing.php';
require_once 'helpers/helper_communications.php';
require_once 'exports/export_wc_orders.php';

function update_orders_cp() {

  $changes = changes_to_orders_cp("gp_orders_cp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_notice("update_orders_cp: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mysql = new Mysql_Wc();

  //If just added to CP Order we need to
  //  - Find out any other rxs need to be added
  //  - Update invoice
  //  - Update wc order count/total
  foreach($changes['created'] as $created) {

    $order = get_full_order($created, $mysql);

    if ( ! $order) {
      log_error("Created Order Missing.  Most likely because cp order has liCount > 0 even though 0 items in order.  If correct, update liCount in CP to 0", $created);
      continue;
    }

    if ($order[0]['order_stage_wc'] == 'wc-processing')
      log_error('Problem: cp order wc-processing created', $order[0]);

    if ($order[0]['order_date_shipped']) {
      log_error("Shipped Order Being Readded", $order);
      continue;
    }

    //1) Add Drugs to Guardian that should be in the order
    //2) Remove drug from guardian that should not be in the order
    //3) Create a fax out transfer for anything removed that is not offered
    //ACTION PATIENT OFF AUTOFILL Notice
    $synced = sync_to_order($order);

    //Patient communication that we are cancelling their order examples include:
    //NEEDS FORM, TRANSFER OUT OF ALL ITEMS, ACTION PATIENT OFF AUTOFILL
    if ($synced['new_count_items'] <= 0) {
      $groups = group_drugs($order, $mysql);
      send_deleted_order_communications($groups);
      log_error("helper_syncing is effectively removing order ".$order[0]['invoice_number'], ['order' => $order, 'synced' => $synced]);
    }

    if ($synced['items_to_sync']) {
      log_notice('sync_to_order necessary: deleting order for it to be readded', $synced['items_to_sync']);
      $mysql->run('DELETE gp_orders FROM gp_orders WHERE invoice_number = '.$order[0]['invoice_number']);
      continue; //DON'T CREATE THE ORDER UNTIL THESE ITEMS ARE SYNCED TO AVOID CONFLICTING COMMUNICATIONS!
    }

    //Needs to be called before "$groups" is set
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    log_notice("Created Order", $order);

    $groups = group_drugs($order, $mysql);

    if ( ! $groups['COUNT_FILLED'] AND $groups['ALL'][0]['item_message_key'] != 'ACTION NEEDS FORM') {
      log_error("SHOULD HAVE BEEN DELETED WITH SYNC CODE ABOVE: Created Order But Not Filling Any?", $groups);
      continue;
    }

    if ( ! $order[0]['pharmacy_name']) {
      log_error("SHOULD HAVE BEEN DELETED WITH SYNC CODE ABOVE: Guardian Order Created But Patient Not Yet Registered in WC so not creating WC Order ".$order[0]['invoice_number'], $order);
      continue;
    }

    $order = helper_update_payment($order, "update_orders_cp: created", $mysql);

    //This is not necessary if order was created by webform, which then created the order in Guardian
    //"order_source": "Webform eRX/Transfer/Refill [w/ Note]"
    if (strpos($order[0]['order_source'], 'Webform') === false)
      export_wc_create_order($order, "update_orders_cp: created");

    send_created_order_communications($groups);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    if ($deleted['order_stage_wc'] == 'wc-processing')
      log_error('Problem: cp order wc-processing deleted', $deleted);

    //Order was Returned to Sender and not logged yet
    if ($deleted['tracking_number'] AND ! $deleted['order_date_returned']) {

      set_payment_actual($deleted['invoice_number'], ['total' => 0, 'fee' => 0, 'due' => 0], $mysql);
      //export_wc_update_order_payment($deleted['invoice_number'], 0); //Don't need this because we are deleting the WC order later

      $update_sql = "
        UPDATE gp_orders SET order_date_returned = NOW() WHERE invoice_number = $deleted[invoice_number]
      ";

      $mysql->run($update_sql);

      log_notice('Confirm this order was returned! Order with tracking number was deleted', $deleted);

      continue;
    }

    //Order #28984
    if ( ! $deleted['patient_id_wc']) {
      log_error('update_orders_cp: cp order deleted - Likely Guardian Order Was Created But Patient Was Not Yet Registered in WC so never created WC Order And No Need To Delete It', $deleted);
      continue;
    }

    export_gd_delete_invoice([$deleted], $mysql);

    //START DEBUG this is getting called on a CP order that is not yet in WC
    $order = get_full_order($deleted, $mysql, true);

    if ($order) {
      log_error('update_orders_cp: cp order deleted (but still exists???) so deleting wc order as well', [$order, $deleted]);
      continue;
    }

    log_notice('update_orders_cp: cp order deleted so deleting wc order as well', [$order, $deleted]);
    //END DEBUG

    export_wc_delete_order($deleted['invoice_number'], "update_orders_cp: cp order deleted $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source] ".json_encode($deleted));

    export_v2_unpend_order([$deleted]);

    $sql = "
      SELECT * FROM gp_patients WHERE patient_id_cp = $deleted[patient_id_cp]
    ";

    $patient = $mysql->run($sql)[0];

    if ( ! $patient)
      log_error('No patient associated with deleted order', ['deleted' => $deleted, 'sql' => $sql]);

    send_deleted_order_communications($deleted);
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $i => $updated) {

    $changed_fields  = changed_fields($updated);
    $day_changes     = [];
    $qty_changes     = [];
    $stage_change_cp = $updated['order_stage_cp'] != $updated['old_order_stage_cp'];

    log_notice("Updated Orders Cp: $updated[invoice_number] ".($i+1)." of ".count($changes['updated']), $changed_fields);

    $order = get_full_order($updated, $mysql);

    if ( ! $order) {
      log_error("Updated Order Missing", $order);
      continue;
    }

    if ($order[0]['order_stage_wc'] == 'wc-processing')
      log_error('Problem: cp order wc-processing updated', [$order[0], $changed_fields]);

    //Normall would run this in the update_order_items.php but we want to wait for all the items to change so that we don't rerun multiple times
    foreach ($order as $item) {

      if ($item['days_dispensed_actual'] AND $item['days_dispensed_default']  != $item['days_dispensed_actual'])
        $day_changes[] = "rx:$item[rx_number] qty:$item[qty_dispensed_default] >>> $item[qty_dispensed_actual] days:$item[days_dispensed_default] >>> $item[days_dispensed_actual] refills:$item[refills_dispensed_default] >>> $item[refills_dispensed_actual]";

      if (
        ($item['qty_dispensed_actual'] AND $item['qty_dispensed_default'] != $item['qty_dispensed_actual']) OR
        ($item['refills_dispensed_actual'] AND $item['refills_dispensed_default'] != $item['refills_dispensed_actual'])
      )
        $qty_changes[] = "rx:$item[rx_number] qty:$item[qty_dispensed_default] >>> $item[qty_dispensed_actual] days:$item[days_dispensed_default] >>> $item[days_dispensed_actual] refills:$item[refills_dispensed_default] >>> $item[refills_dispensed_actual]";

      $actual_sig_qty_per_day = $item['days_dispensed_actual'] ? round($item['qty_dispensed_actual']/$item['days_dispensed_actual'], 1) : 0;
      if ($actual_sig_qty_per_day AND $actual_sig_qty_per_day != round($item['sig_qty_per_day'], 1))
        log_error("sig parsing error '$item[sig_actual]' $item[sig_qty_per_day] (default) != $actual_sig_qty_per_day $item[qty_dispensed_actual]/$item[days_dispensed_actual] (actual)", $item);
    }

    //Do we need to update the order in WC or it's invoice?
    if ($order[0]['count_items'] != $order[0]['count_filled']) {

      log_error("update_orders_cp: count filled updated ".$order[0]['count_items']." (count items) != ".$order[0]['count_filled']." (count filled)", [$order, $updated]);

      $items_to_sync = sync_to_order($order, $updated);

      list($target_date, $target_rxs) = get_sync_to_date($order);
      $order = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

      $order = helper_update_payment($order,  "update_orders_cp: updated - count filled changes ".$order[0]['count_items']." (count items) != ".$order[0]['count_filled']." (count filled)", $mysql);
      export_wc_update_order($order);

    } else if ($day_changes) {
      //Updates invoice with new days/price/qty/refills.
      $order = helper_update_payment($order,  "update_orders_cp: updated - dispensing day changes ".implode(', ', $day_changes), $mysql);
      export_wc_update_order($order); //Price will also have changed

    } else if ($qty_changes) {
      //Updates invoice with new qty/refills.  Prices should not have changed so no need to update WC
      $order = helper_update_payment($order,  "update_orders_cp: updated - dispensing qty changes ".implode(', ', $qty_changes), $mysql);
    }

    if ($stage_change_cp AND $updated['order_date_shipped']) {
      $groups = group_drugs($order, $mysql);
      export_v2_unpend_order($order);
      export_wc_update_order_status($order); //Update status from prepare to shipped
      send_shipped_order_communications($groups);
      log_notice("Updated Order Shipped", $order);
    }

    if ($stage_change_cp AND $updated['order_date_dispensed']) {
      $groups = group_drugs($order, $mysql);
      export_gd_publish_invoice($order);
      send_dispensed_order_communications($groups);
      log_notice("Updated Order Dispensed", $order);
    }

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration

  }

  //TODO Upsert Salseforce Order Status, Order Tracking

  }
