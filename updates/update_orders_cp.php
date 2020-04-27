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

  //Usually we could just do old_days_dispensed_default != days_dispensed_default AND old_days_dispensed_actual != days_dispensed_actual
  //BUT those are on the order_item level.  We don't want to handle them in update_order_items.php because we need to batch the changes
  //so we don't need to rerun the invoice update on every call.  We only run this when the order is dispensed, otherwise it could detect
  //the same change multiple times (days_dispensed_actual is first set on the Printed/Processed stage) and then would trigger a 2nd time
  //when Dispensed and maybe a 3rd time when shipped.
  function detect_dispensing_changes($order) {

    $day_changes     = [];
    $qty_changes     = [];
    $mysql = new Mysql_Wc();

    //Normall would run this in the update_order_items.php but we want to wait for all the items to change so that we don't rerun multiple times
    foreach ($order as $item) {

      //! $updated['order_date_dispensed'] otherwise triggered twice, once one stage: Printed/Processed and again on stage:Dispensed
      if ($item['days_dispensed_default'] != $item['days_dispensed_actual'])
        $day_changes[] = "rx:$item[rx_number] qty:$item[qty_dispensed_default] >>> $item[qty_dispensed_actual] days:$item[days_dispensed_default] >>> $item[days_dispensed_actual] refills:$item[refills_dispensed_default] >>> $item[refills_dispensed_actual] item:".json_encode($item);

      //! $updated['order_date_dispensed'] otherwise triggered twice, once one stage: Printed/Processed and again on stage:Dispensed
      if ($item['qty_dispensed_default'] != $item['qty_dispensed_actual'] OR (($item['refills_dispensed_default']+0) != $item['refills_dispensed_actual']))
        $qty_changes[] = "rx:$item[rx_number] qty:$item[qty_dispensed_default] >>> $item[qty_dispensed_actual] days:$item[days_dispensed_default] >>> $item[days_dispensed_actual] refills:$item[refills_dispensed_default] >>> $item[refills_dispensed_actual] item:".json_encode($item);

      //! $updated['order_date_dispensed'] otherwise triggered twice, once one stage: Printed/Processed and again on stage:Dispensed
      $sig_qty_per_day_actual = $item['days_dispensed_actual'] ? round($item['qty_dispensed_actual']/$item['days_dispensed_actual'], 1) : 0;
      $mysql->run("
        UPDATE gp_rxs_single SET sig_qty_per_day_actual = $sig_qty_per_day_actual WHERE rx_number = $item[rx_number]
      ");

      if ($item['days_dispensed_actual'] AND $item['refills_dispensed'] AND ($item['days_dispensed_actual'] > DAYS_MAX OR $item['days_dispensed_actual'] < DAYS_MIN))
        log_error("check days dispensed is not within limits and it's not out of refills: ".DAYS_MIN." < $item[days_dispensed_actual] < ".DAYS_MAX, $item);
      else if ($sig_qty_per_day_actual AND $sig_qty_per_day_actual != round($item['sig_qty_per_day_default'], 1) AND $sig_qty_per_day_actual != round($item['sig_qty_per_day_default']*2, 1)) { // *2 is a hack for "as needed" being different right now
        log_error("sig parsing error Updating to Actual Qty_Per_Day '$item[sig_actual]' $item[sig_qty_per_day_default] (default) != $sig_qty_per_day_actual $item[qty_dispensed_actual]/$item[days_dispensed_actual] (actual)", $item);
      }
    }

    log_notice("update_order_cp detect_dispensing_changes", ['order' => $order, 'day_changes' => $day_changes, 'qty_changes' => $qty_changes]);
    return ['day_changes' => $day_changes, 'qty_changes' => $qty_changes];
  }

  //If just added to CP Order we need to
  //  - Find out any other rxs need to be added
  //  - Update invoice
  //  - Update wc order count/total
  foreach($changes['created'] as $created) {

    //Overrite Rx Messages everytime a new order created otherwis same message would stay for the life of the Rx
    $order = get_full_order($created, $mysql, true);

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
      order_hold_notice($groups);
      log_notice("update_orders_cp sync_to_order is effectively removing order ".$order[0]['invoice_number'], ['order' => $order, 'synced' => $synced]);
    }

    if ($synced['items_to_sync']) {
      log_notice('update_orders_cp sync_to_order necessary on CREATE: deleting order for it to be readded', $synced);
      $mysql->run('DELETE gp_orders FROM gp_orders WHERE invoice_number = '.$order[0]['invoice_number']); //Force created to run again after the changes take place
      continue; //DON'T CREATE THE ORDER UNTIL THESE ITEMS ARE SYNCED TO AVOID CONFLICTING COMMUNICATIONS!
    }

    //Needs to be called before "$groups" is set
    list($target_date, $target_rxs) = get_sync_to_date($order);
    $order  = set_sync_to_date($order, $target_date, $target_rxs, $mysql);

    export_v2_pend_order($order, $mysql);

    log_notice("Created & Pended Order", ['order' => $order, 'synced' => $synced]);

    $groups = group_drugs($order, $mysql);

    // 3 Steps of ACTION NEEDS FORM:
    // 1) Here.  update_orders_cp created (surescript came in and created a CP order)
    // 2) Same cycle: update_order_wc deleted (since WC doesn't have the new order yet)
    // 3) Next cycle: update_orders_cp deleted (not sure yet why it gets deleted from CP)
    if ( ! $order[0]['pharmacy_name']) { //Can't test for rx_message_key == 'ACTION NEEDS FORM' because other messages can take precedence
      needs_form_notice($groups);
      log_notice("update_orders_cp created: Guardian Order Created But Patient Not Yet Registered in WC so not creating WC Order ".$order[0]['invoice_number'], $order);
      continue;
    }

    $order = helper_update_payment($order, "update_orders_cp: created", $mysql);

    //This is not necessary if order was created by webform, which then created the order in Guardian
    //"order_source": "Webform eRX/Transfer/Refill [w/ Note]"
    if (strpos($order[0]['order_source'], 'Webform') === false)
      export_wc_create_order($order, "update_orders_cp: created");

    if ( ! $groups['COUNT_FILLED']) {
      order_hold_notice($groups);
      log_error("update_orders_cp: Order Hold hopefully due to 'NO ACTION MISSING GSN' otherwise should have been deleted with sync code above", $groups);
    } else {
      send_created_order_communications($groups);
    }

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {


    //Order #28984, #29121, #29105
    if ( ! $deleted['patient_id_wc']) {
      //Likely
      //  (1) Guardian Order Was Created But Patient Was Not Yet Registered in WC so never created WC Order (and No Need To Delete It)
      //  (2) OR Guardian Order had items synced to/from it, so was deleted and readded, which effectively erases the patient_id_wc
      log_error('update_orders_cp: cp order deleted - no patient_id_wc', $deleted);
    } else {
      log_notice('update_orders_cp: cp order deleted so deleting wc order as well', $deleted);
    }

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

    export_gd_delete_invoice([$deleted], $mysql);

    export_wc_delete_order($deleted['invoice_number'], "update_orders_cp: cp order deleted $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source] ".json_encode($deleted));

    export_v2_unpend_order([$deleted]);

    $sql = "
      SELECT * FROM gp_patients WHERE patient_id_cp = $deleted[patient_id_cp]
    ";

    $patient = $mysql->run($sql)[0];

    if ( ! $patient)
      log_error('No patient associated with deleted order (Patient Deactivated/Deceased/Moved out of State)', ['deleted' => $deleted, 'sql' => $sql]);

    //We should be able to delete wc-confirm-* from CP queue without triggering an order cancel notice
    if ($deleted['count_items'])
      order_canceled_notice($deleted); //We passed in $deleted because there is not $order to make $groups
    else {
      no_rx_notice($deleted);
      log_error("update_orders_cp deleted: count_items == 0 so calling no_rx_notice() rather than order_canceled_notice()", $deleted);
    }
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $i => $updated) {

    $changed_fields  = changed_fields($updated);
    $stage_change_cp = $updated['order_stage_cp'] != $updated['old_order_stage_cp'];

    log_notice("Updated Orders Cp: $updated[invoice_number] ".($i+1)." of ".count($changes['updated']), $changed_fields);

    $order = get_full_order($updated, $mysql);

    if ( ! $order) {
      log_error("Updated Order Missing", $order);
      continue;
    }

    if ($stage_change_cp AND $updated['order_date_shipped']) {
      $groups = group_drugs($order, $mysql);
      export_v2_unpend_order($order);
      export_wc_update_order_status($order); //Update status from prepare to shipped
      send_shipped_order_communications($groups);
      log_notice("Updated Order Shipped", $order);
      continue;
    }

    if ($stage_change_cp AND $updated['order_date_dispensed']) {

      $dispensing_changes = detect_dispensing_changes($order);

      if ($dispensing_changes['day_changes']) {
        //Updates invoice with new days/price/qty/refills.
        $order = helper_update_payment($order,  "update_orders_cp: updated - dispensing day changes ".implode(', ', $dispensing_changes['day_changes']), $mysql);
        export_wc_update_order($order); //Price will also have changed

      } else if ($dispensing_changes['qty_changes']) {
        //Updates invoice with new qty/refills.  Prices should not have changed so no need to update WC
        $order = export_gd_update_invoice($order, "update_orders_cp: updated - dispensing qty changes ".implode(', ', $dispensing_changes['qty_changes']), $mysql);
      }

      $groups = group_drugs($order, $mysql);

      export_gd_publish_invoice($order);
      export_gd_print_invoice($order);
      send_dispensed_order_communications($groups);
      log_notice("Updated Order Dispensed", $order);
      continue;
    }


    //We won't sync new drugs to the order, but if a new drug comes in that we are not filling, we will remove it
    $synced = sync_to_order($order, $updated);

    if ($synced['items_to_sync']) {

      log_error("update_orders_cp sync_to_order necessary on UPDATE:", [$updated, $synced['items_to_sync']]);
      $mysql->run("UPDATE gp_orders SET count_items = 0 WHERE invoice_number = {$order[0]['invoice_number']}"); //Force updated to run again after the changes take place
      continue;
    }

    if ($updated['count_items'] != $updated['old_count_items']) {

      $log = "update_orders_cp: count items changed $updated[invoice_number]: $updated[old_count_items] -> $updated[count_items]";
      log_error($log, [$order, $updated]);

      foreach($order as $item) {

        if ($item['count_pended_total'] AND ! $item['days_dispensed'])
          unpend_pick_list($item);

        if ( ! $item['count_pended_total'] AND $item['days_dispensed'])
          v2_pend_item($item, $mysql);
      }

      $order = helper_update_payment($order, $log, $mysql); //This also updates payment
      export_wc_update_order($order);
      continue;
    }

    //Address Changes
    //Stage Change
    //Order_Source Change (now that we overwrite when saving webform)
    log_notice("update_orders_cp updated: no action taken $updated[invoice_number]", [$order, $updated, $changed_fields]);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration

  }
  //TODO Upsert Salseforce Order Status, Order Tracking
}
