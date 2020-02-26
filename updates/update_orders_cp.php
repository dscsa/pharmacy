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
    $items_to_sync = sync_to_order($order);
    if ($items_to_sync) {
      log_error('sync_to_order necessary: deleting order for it to be readded', $items_to_sync);
      $mysql->run('DELETE gp_orders FROM gp_orders WHERE invoice_number = '.$order[0]['invoice_number']);
      continue; //DON'T CREATE THE ORDER UNTIL THESE ITEMS ARE SYNCED TO AVOID CONFLICTING COMMUNICATIONS!
    }

    //Needs to be called before "$groups" is set
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    log_notice("Created Order", $order);

    $groups = group_drugs($order, $mysql);

    if ( ! $groups['COUNT_FILLED'] AND $groups['ALL'][0]['item_message_key'] != 'ACTION NEEDS FORM') {
      log_info("Created Order But Not Filling Any?", $groups);
      continue;
    }

    if ( ! $order[0]['pharmacy_name']) {
      log_notice("Guardian Order Created But Patient Not Yet Registered in WC so not creating WC Order ".$order[0]['invoice_number']);
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

      return log_notice('Confirm this order was returned! Order with tracking number was deleted', $deleted);
    }

    export_gd_delete_invoice([$deleted], $mysql);

    //START DEBUG this is getting called on a CP order that is not yet in WC
    $order = get_full_order($deleted, $mysql);
    log_error('update_orders_cp: cp order deleted so deleting wc order as well', [$order, $deleted]);
    //END DEBUG

    export_wc_delete_order($deleted['invoice_number'], "update_orders_cp: $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source] ".json_encode($deleted));

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

    log_notice("Updated Orders Cp: $updated[invoice_number] ".($i+1)." of ".count($changes['updated']), $changed_fields);

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

      //order_stage_cp and tracking_number, order_date_shipped
      if (count($changed_fields) > 3) {
        export_gd_publish_invoice($order);
        export_wc_update_order($order);
        log_error("Updated Order Dispensed: changed fields ".$order[0]['invoice_number'], $changed_fields);
      } else if ($stage_change_cp) {
        log_notice("Updated Order Shipped: status change only ".$order[0]['invoice_number']);
      } else {
        log_error("Shipped Order Was Updated?", $order);
      }

      export_v2_unpend_order($order);
      send_shipped_order_communications($groups);

      log_info("Updated Order Shipped", $order);
      continue;
    }

    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    if ($updated['order_date_dispensed']) {

      $day_changes = [];
      $qty_changes = [];

      foreach ($order as $item) {

        if ($item['days_dispensed_default']    != $item['days_dispensed_actual'])
          $day_changes[] = "rx:$item[rx_number] qty:$item[qty_dispensed_default] >>> $item[qty_dispensed_actual] days:$item[days_dispensed_default] >>> $item[days_dispensed_actual] refills:$item[refills_dispensed_default] >>> $item[refills_dispensed_actual]";

        else if (
          $item['qty_dispensed_default']     != $item['qty_dispensed_actual'] OR
          $item['refills_dispensed_default'] != $item['refills_dispensed_actual']
        )
          $qty_changes[] = "rx:$item[rx_number] qty:$item[qty_dispensed_default] >>> $item[qty_dispensed_actual] days:$item[days_dispensed_default] >>> $item[days_dispensed_actual] refills:$item[refills_dispensed_default] >>> $item[refills_dispensed_actual]";

        $actual_sig_qty_per_day = $item['days_dispensed_actual'] ? round($item['qty_dispensed_actual']/$item['days_dispensed_actual'], 3) : 0;
        if ($actual_sig_qty_per_day AND $actual_sig_qty_per_day != $item['sig_qty_per_day'])
          log_error("sig parsing error '$item[sig_actual]' $item[sig_qty_per_day] (default) != $actual_sig_qty_per_day $item[qty_dispensed_actual]/$item[days_dispensed_actual] (actual)", $item);
      }

      //order_stage_cp and order_date_dispensed
      if ($day_changes) {

        $order = helper_update_payment($order,  "update_orders_cp: updated - dispensing day changes", $mysql);
        export_gd_publish_invoice($order);
        export_wc_update_order($order);
        log_notice("Updated Order Dispensed: dispensing day changes: ".implode('; ', $dispensing_changes).' '.$order[0]['invoice_number'], [$item, $changed_fields]);

      }
      else if ($qty_changes) {
        export_gd_publish_invoice($order);
        log_notice("Updated Order Dispensed: dispensing qty/refill changes: ".implode('; ', $dispensing_changes).' '.$order[0]['invoice_number'], [$item, $changed_fields]);

      } else if (count($changed_fields) > 2) {
        //Usually some type of address change in addition to be dispensed
        $order = helper_update_payment($order,  "update_orders_cp: updated > 2 fields", $mysql);
        export_gd_publish_invoice($order);
        export_wc_update_order($order);
        log_error("Updated Order Dispensed: changed fields ".$order[0]['invoice_number'], $changed_fields);

      } else if ($stage_change_cp) {
        log_notice("Updated Order Dispensed: status change only ".$order[0]['invoice_number']);
      } else {
        log_error("Dispensed Order Was Updated? ".$order[0]['invoice_number'], $changed_fields);
      }

      export_v2_unpend_order($order);
      send_dispensed_order_communications($groups);

      log_info("Updated Order Dispensed", $order);
      continue;
    }

    if ($updated['count_filled'] == $order[0]['count_filled']) {
      export_wc_update_order($order);

      if ($stage_change_cp)
        log_info("Updated Order stage_change_cp:", [$changed_fields, $order]);
      else if ($updated['count_items'] != $updated['old_count_items'])
        log_notice("Updated Order count_items changed but count_filled did not:", [$changed_fields, $order]);
      else
        log_error("Updated Order abnormal change", [$changed_fields, $order]);

      continue;
    }

    //Usually count_items changed
    if ($stage_change_cp AND count($changed_fields) == 1) {
      log_info("Updated Order Stage Change", [$changed_fields, $order]);
      continue;
    }

    //Array Union to make sure only those 4 changes were made
    if (count($changed_fields + ['order_stage_cp' => '', 'order_address1' => '', 'order_address2' => '', 'order_city' => '', 'order_zip' => '']) == 5) {
      log_notice("Updated Order Address Change", [$changed_fields, $order]);
      continue;
    }

    if (@$changed_fields['count_items'] AND count($changed_fields) == 1) {
      log_info("Updated Order: count_items changed ".$order[0]['invoice_number'], $changed_fields);
      continue; //As long as count_filled doesn't change then this shouldn't matter
    }

    //Usually an Address Change
    log_error("Updated Order: Unknown Change ".$order[0]['invoice_number'], $changed_fields);

    $order = helper_update_payment($order,  "update_orders_cp: updated - unknown change", $mysql);
    export_wc_update_order($order);

    send_updated_order_communications($groups, $changed_fields);

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
