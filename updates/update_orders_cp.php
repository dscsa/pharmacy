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

  log_info("update_orders_cp: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();

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


    //Order was Returned to Sender
    if ($deleted['tracking_number']) {

      set_payment_actual($deleted['invoice_number'], ['total' => 0, 'fee' => 0, 'due' => 0], $mysql);
      export_wc_update_order_payment($deleted['invoice_number'], 0);

      $sql = "
        UPDATE gp_orders SET order_date_returned = GETDATE() WHERE invoice_number = $deleted[invoice_number]
      ";

      $mysql->run($sql);

      return log_error('Confirm this order was returned! Order with tracking number was deleted', get_defined_vars());
    }

    export_gd_delete_invoice([$deleted]);

    export_wc_delete_order([$deleted]);

    export_v2_unpend_order([$deleted]);

    $sql = "
      SELECT * FROM gp_patients WHERE patient_id_cp = $deleted[patient_id_cp]
    ";

    $patient = $mysql->run($sql)[0];

    if ( ! $patient)
      log_error('No patient associated with deleted order', get_defined_vars());

    if ($patient['pharmacy_name']) //Cindy deletes "Needs Form" orders and we don't want to confuse them with a canceled communication
      send_deleted_order_communications([$deleted]);
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
      export_gd_publish_invoice($order);
      export_wc_update_order_metadata($order);
      export_wc_update_order_shipping($order);
      export_v2_unpend_order($order);
      send_shipped_order_communications($groups);
      log_notice("Updated Order Shipped", get_defined_vars());
      continue;
    }

    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    if ($stage_change AND $updated['order_date_dispensed']) {
      helper_update_payment($order, $mysql);
      export_gd_publish_invoice($order);
      export_wc_update_order_metadata($order);
      export_wc_update_order_shipping($order);
      export_v2_unpend_order($order);
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
