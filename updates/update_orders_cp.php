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

  log_error("update_orders_cp: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mysql = new Mysql_Wc();

  //If just added to CP Order we need to
  //  - Find out any other rxs need to be added
  //  - Update invoice
  //  - Update wc order count/total
  foreach($changes['created'] as $created) {

    $order = get_full_order($created, $mysql);

    if ( ! $order) {
      log_error("Created Order Missing", $created);
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
    $items_to_sync = sync_to_order($order);
    if ($items_to_sync) {
      log_info('sync_to_order: created', [$items_to_sync, $order]);
      $mysql->run('DELETE gp_orders FROM gp_orders WHERE invoice_number = '.$order[0]['invoice_number']);
      continue; //DON'T CREATE THE ORDER UNTIL THESE ITEMS ARE SYNCED TO AVOID CONFLICTING COMMUNICATIONS!
    }

    //Needs to be called before "$groups" is set
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    $groups = group_drugs($order, $mysql);

    if ( ! $groups['COUNT_FILLED'] AND $groups['ALL'][0]['item_message_key'] != 'ACTION NEEDS FORM') {
      log_info("Created Order But Not Filling Any?", $groups);
      continue;
    }

    $order = helper_update_payment($order, $mysql);

    //This is not necessary if order was created by webform, which then created the order in Guardian
    //"order_source": "Webform eRX",  "item_added_by": "WEBFORM",
    export_wc_create_order($order, "update_orders_cp: created");

    send_created_order_communications($groups);

    log_info("Created Order", $groups);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    if ($order[0]['order_stage_wc'] == 'wc-processing')
      log_error('Problem: cp order wc-processing deleted', $deleted);

    //Order was Returned to Sender and not logged yet
    if ($deleted['tracking_number'] AND ! $deleted['order_date_returned']) {

      set_payment_actual($deleted['invoice_number'], ['total' => 0, 'fee' => 0, 'due' => 0], $mysql);
      //export_wc_update_order_payment($deleted['invoice_number'], 0); //Don't need this because we are deleting the WC order later

      $update_sql = "
        UPDATE gp_orders SET order_date_returned = NOW() WHERE invoice_number = $deleted[invoice_number]
      ";

      $mysql->run($update_sql);

      return log_notice('Confirm this order was returned! Order with tracking number was deleted', $deleted);
    }

    export_gd_delete_invoice([$deleted], $mysql);

    export_wc_delete_order($deleted['invoice_number'], "update_orders_cp: $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source]");

    export_v2_unpend_order([$deleted]);

    $sql = "
      SELECT * FROM gp_patients WHERE patient_id_cp = $deleted[patient_id_cp]
    ";

    $patient = $mysql->run($sql)[0];

    if ( ! $patient)
      log_error('No patient associated with deleted order', ['deleted' => $deleted, 'sql' => $sql]);

    if ( ! empty($patient['pharmacy_name'])) //Cindy deletes "Needs Form" orders and we don't want to confuse them with a canceled communication
      send_deleted_order_communications([$deleted]);
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $i => $updated) {

    $changed_fields = changed_fields($updated);

    log_error("Updated Orders Cp: $updated[invoice_number] ".($i+1)." of ".count($changes['updated']), $changed_fields);

    $order = get_full_order($updated, $mysql);

    if ( ! $order) {
      log_error("Updated Order Missing", $order);
      continue;
    }

    if ($order[0]['order_stage_wc'] == 'wc-processing')
      log_error('Problem: cp order wc-processing updated', [$order[0], $changed_fields]);

    //Remove only (e.g. new surescript comes in), let's not add more drugs to their order since communication already went out
    $items_to_sync = sync_to_order($order, $updated);
    if ($items_to_sync) {
      log_info('sync_to_order: updated', $items_to_sync);
    }

    $stage_change_cp = ($updated['order_stage_cp'] != $updated['old_order_stage_cp']);

    //Needs to be called before "$groups" is set
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    $groups = group_drugs($order, $mysql);

    if ($updated['order_date_shipped']) {

      if ( ! $stage_change_cp) {
        log_error("Shipped Order Was Updated?", $order);
      }

      export_gd_publish_invoice($order);
      export_wc_update_order($order);
      export_v2_unpend_order($order);
      send_shipped_order_communications($groups);
      log_error("Updated Order Shipped", $order[0]['invoice_number']);
      log_info("Updated Order Shipped", $order);
      continue;
    }

    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    if ($updated['order_date_dispensed']) {

      if ( ! $stage_change_cp) {
        log_error("Dispensed Order Was Updated?", $order);
      }

      $order = helper_update_payment($order, $mysql);
      export_gd_publish_invoice($order);
      export_wc_update_order($order);
      export_v2_unpend_order($order);
      send_dispensed_order_communications($groups);
      log_error("Updated Order Dispensed", $order[0]['invoice_number']);
      log_info("Updated Order Dispensed", $order);
      continue;
    }

    if ($updated['count_filled'] == $order[0]['count_filled']) {
      export_wc_update_order($order);

      if ($stage_change_cp)
        log_info("Updated Order stage_change_cp:", $order);
      else
        log_error("Updated Order Count Not Changed", $order);

      continue;
    }

    if ( ! $stage_change_cp AND $updated['count_filled'] == $order[0]['count_filled'])
      log_error('cp order abnormal change', [
        'old_count_filled' => $updated['count_filled'],
        'new_count_filled' => $order[0]['count_filled'],
        'updated' => $updated,
        'stage_change' => "$updated[old_order_stage_cp] >>> $updated[order_stage_cp]",
        'order[0]' => $order[0]
      ]);

    //Usually count_items changed
    $order = helper_update_payment($order, $mysql);
    export_wc_update_order($order);

    send_updated_order_communications($groups);

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
