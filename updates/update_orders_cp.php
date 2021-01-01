<?php

require_once 'helpers/helper_full_order.php';
require_once 'helpers/helper_payment.php';
require_once 'helpers/helper_syncing.php';
require_once 'helpers/helper_communications.php';
require_once 'exports/export_wc_orders.php';
require_once 'exports/export_cp_orders.php';

use Sirum\Logging\SirumLog;

function update_orders_cp($changes) {

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
  echo $msg;
  log_info("update_orders_cp: all changes. $msg", [
    'deleted_count' => $count_deleted,
    'created_count' => $count_created,
    'updated_count' => $count_updated
  ]);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  //If just added to CP Order we need to
  //  - Find out any other rxs need to be added
  //  - Update invoice
  //  - Update wc order count/total
    $loop_timer = microtime(true);
    foreach($changes['created'] as $created) {

        SirumLog::$subroutine_id = "orders-cp-created-".sha1(serialize($created));

        //Overrite Rx Messages everytime a new order created otherwis same message would stay for the life of the Rx

        SirumLog::debug(
          "get_full_order: Carepoint Order created",
          [
            'invoice_number' => $created['invoice_number'],
            'created' => $created,
            'source'  => 'CarePoint',
            'type'    => 'orders',
            'event'   => 'created'
          ]
        );

        $duplicate = get_current_orders($mysql, ['patient_id_cp' => $created['patient_id_cp']]);

        if (count($duplicate) > 1 AND $duplicate[0]['invoice_number'] != $created['invoice_number']) {
          SirumLog::alert(
            "Created Carepoint Order Seems to be a duplicate",
            [
              'invoice_number' => $created['invoice_number'],
              'created' => $created,
              'duplicate' => $duplicate
            ]
          );

          //Not sure what we should do here. Delete it?
          //Instance where current order doesn't have all drugs, so patient/staff add a second order with the drug.  Merge orders?
          if ($created['count_items'] == 0)
            export_cp_remove_order($created['invoice_number'], "Duplicate of ".$duplicate[0]['invoice_number']);

          continue;
        }

        $order = load_full_order($created, $mysql, true);

        if ( ! $order) {
            SirumLog::debug(
                "Created Order Missing.  Most likely because cp order has liCount >
                  0 even though 0 items in order.  If correct, update liCount in CP to 0",
                ['order' => $order]
            );
            continue;
        }

        SirumLog::debug(
          "Order found for created order",
          [
            'invoice_number' => $order[0]['invoice_number'],
            'order'          => $order,
            'created'        => $created
          ]
        );

        //TODO Add Special Case for Webform Transfer [w/ Note] here?

        //Item would have already been pended from order-item-created::load_full_item
        if ($created['order_status'] == "Surescripts Authorization Denied") {
          SirumLog::error(
            "Order CP Created - Deleting and Unpending Order $created[invoice_number] because Surescripts Authorization Denied. Can we remove the v2_unpend_order below because it get called on the next run?",
            [
              'invoice_number' => $created['invoice_number'],
              'created' => $created,
              'source'  => 'CarePoint',
              'type'    => 'orders',
              'event'   => 'created'
            ]
          );

          $date = "Created:".date('Y-m-d H:i:s');

          //Don't think we need this because CP doesn't combine multiple denials into one order
          $drugs = [];
          foreach($order as $item) {
            $drugs[] = $item['drug_name'];
          }

          $salesforce = [
            "subject"   => "SureScripts refill request denied for ".implode(', ', $drugs),
            "body"      => "Order $created[invoice_number] deleted because provider denied SureScripts refill request for ".implode(', ', $drugs),
            "contact"   => "{$order[0]['first_name']} {$order[0]['last_name']} {$order[0]['birth_date']}"
          ];

          create_event($salesforce['subject'], [$salesforce]);

          //TODO Why do we need to explicitly unpend?  Deleting an order in CP should trigger the deleted loop on next run, which should unpend
          //But it seemed that this didn't happen for Order 53684
          export_v2_unpend_order($order, $mysql);
          export_cp_remove_order($created['invoice_number'], 'Surescripts Denied');
          continue; //Not sure what we should do here.  Process them?  Patient communication?
        }

        if ($created['order_status'] == "Surescripts Authorization Approved")
          SirumLog::error(
            "Surescripts Authorization Approved. Created.  What to do here?  Keep Order $created[invoice_number]? Delete Order? Depends on Autofill settings?",
            [
              'invoice_number'   => $created['invoice_number'],
              'count_items'      => count($order)." / ".@$order['count_items'],
              'patient_autofill' => $order[0]['patient_autofill'],
              'rx_autofill'      => $order[0]['rx_autofill'],
              'order'            => $order
            ]
          );

        if ($order[0]['order_stage_wc'] == 'wc-processing') {
            SirumLog::debug(
                'Problem: cp order wc-processing created '.$order[0]['invoice_number'],
                [
                  'invoice_number' => $order[0]['invoice_number'],
                  'order'          => $order
                ]
            );
        }

        if ($order[0]['order_date_dispensed']) {
            $reason = "update_orders_cp: dispened/shipped order being readded";

            export_wc_create_order($order, $reason);
            $order = export_gd_publish_invoice($order, $mysql);
            export_gd_print_invoice($order);
            SirumLog::debug(
              'Dispensed/Shipped order is missing and is being added back to the wc and gp tables',
              [
                'invoice_number' => $order[0]['invoice_number'],
                'order'          => $order
              ]
            );

            continue;
        }

        //Patient communication that we are cancelling their order examples include:
        //NEEDS FORM, ORDER HOLD WAITING FOR RXS, TRANSFER OUT OF ALL ITEMS, ACTION PATIENT OFF AUTOFILL
        //count_items instead of count_filled because it might be a manually added item, that we are not filling but that the pharmacist is using as a placeholder/reminder e.g 54732
        if ($order[0]['count_items'] == 0 AND $order[0]['count_filled'] == 0 AND $order[0]['count_to_add'] == 0 AND ! is_webform_transfer($order[0])) {

          SirumLog::warning(
            "update_orders_cp: created. no drugs to fill. removing order {$order[0]['invoice_number']}. Can we remove the v2_unpend_order below because it get called on the next run?",
            [
              'invoice_number' => $order[0]['invoice_number'],
              'count_filled'   => $order[0]['count_filled'],
              'count_items'    => $order[0]['count_items'],
              'order'          => $order
            ]
          );

          $groups = group_drugs($order, $mysql);
          order_hold_notice($groups);

          //TODO Remove/Cancel WC Order Here

          //TODO Why do we need to explicitly unpend?  Deleting an order in CP should trigger the deleted loop on next run, which should unpend
          //But it seemed that this didn't happen for Order 53684
          export_v2_unpend_order($order, $mysql);
          export_cp_remove_order($order[0]['invoice_number'], 'Created Empty');
          continue;
        }

        if ($order[0]['count_to_remove'] > 0 OR $order[0]['count_to_add'] > 0) {

          SirumLog::debug(
            'update_orders_cp: created. adding wc-order then skipping order '.$order[0]['invoice_number'].' because items still need to be removed/added',
            [
              'invoice_number'  => $order[0]['invoice_number'],
              'count_filled'    => $order[0]['count_filled'],
              'count_items'     => $order[0]['count_items'],
              'count_to_remove' => $order[0]['count_to_remove'],
              'count_to_add'    => $order[0]['count_to_add'],
              'count_added'     => $order[0]['count_added'],
              'order'           => $order
            ]
          );

          //Force created loop to run again so that patient gets "Order Created Communication" and not just an update one
           $mysql->run("
            DELETE gp_orders
            FROM gp_orders
            WHERE invoice_number = {$order[0]['invoice_number']}
          ");

          export_wc_create_order($order, "update_orders_cp: skipped cp because items still need to be removed/added");

          //DON'T CREATE THE ORDER UNTIL THESE ITEMS ARE SYNCED TO AVOID CONFLICTING COMMUNICATIONS!
          continue;
        }

        //Needs to be called before "$groups" is set
        $order  = sync_to_date($order, $mysql);
        $groups = group_drugs($order, $mysql);

        /*
         * 3 Steps of ACTION NEEDS FORM:
         * 1) Here.  update_orders_cp created (surescript came in and created a CP order)
         * 2) Same cycle: update_order_wc deleted (since WC doesn't have the new order yet)
         * 3) Next cycle: update_orders_cp deleted (not sure yet why it gets deleted from CP)
         * Can't test for rx_message_key == 'ACTION NEEDS FORM' because other messages can take precedence
         */

        if ( ! $order[0]['pharmacy_name']) {
            needs_form_notice($groups);
            SirumLog::notice(
                "update_orders_cp created: Guardian Order Created But
                  Patient Not Yet Registered in WC so not creating WC Order",
                [
                  'invoice_number' => $order[0]['invoice_number'],
                  'order' => $order
                ]
            );
            continue;
        }

        //This is not necessary if order was created by webform, which then created the order in Guardian
        //"order_source": "Webform eRX/Transfer/Refill [w/ Note]"
        if ( ! is_webform($order[0])) {

          SirumLog::debug(
            "Creating order ".$order[0]['invoice_number']." in woocommerce because source is not the Webform",
            [
              'invoice_number' => $order[0]['invoice_number'],
              'source'         => $order[0]['order_source'],
              'order'          => $order,
              'groups'         => $groups
            ]
          );

          export_wc_create_order($order, "update_orders_cp: created");
        }

        if ($order[0]['count_filled'] > 0) {
          send_created_order_communications($groups);
          continue;
        }

        if (is_webform_transfer($order[0])) {
          continue; // order hold notice not necessary for transfers
        }

        if ($order[0]['count_to_add'] == 0) {
          continue; // order hold notice not necessary if we are adding items on next go-around
        }

        order_hold_notice($groups, true);
        SirumLog::debug(
          "update_orders_cp: Order Hold hopefully due to 'NO ACTION MISSING GSN' otherwise should have been deleted with sync code above",
          [
            'invoice_number' => $order[0]['invoice_number'],
            'order' => $order,
            'groups' => $groups
          ]
        );

        //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
    } // END created loop
    log_timer('orders-cp-created', $loop_timer, $count_created);


    /*
     * If just deleted from CP Order we need to
     *  - set "days_dispensed_default" and "qty_dispensed_default" to 0
     *  - unpend in v2 and save applicable fields
     *  - if last line item in order, find out any other rxs need to be removed
     *  - update invoice
     *  - update wc order total
     */
    $loop_timer = microtime(true);
    foreach ($changes['deleted'] as $deleted) {

      SirumLog::$subroutine_id = "orders-cp-deleted-".sha1(serialize($deleted));

      SirumLog::debug(
          "update_orders_cp: carepoint order $deleted[invoice_number] has been deleted",
          [
            'source'         => 'CarePoint',
            'event'          => 'deleted',
            'type'           => 'orders',
            'invoice_number' => $deleted['invoice_number'],
            'deleted'        => $deleted
          ]
      );

      if ($deleted['order_status'] == "Surescripts Authorization Denied")
        SirumLog::error(
          "Surescripts Authorization Denied. Deleted. What to do here?",
          [
            'invoice_number' => $deleted['invoice_number'],
            'deleted' => $deleted,
            'source'  => 'CarePoint',
            'type'    => 'orders',
            'event'   => 'deleted'
          ]
        );

      if ($deleted['order_status'] == "Surescripts Authorization Approved")
        SirumLog::error(
          "Surescripts Authorization Approved. Deleted.  What to do here?",
          [
            'invoice_number'   => $deleted['invoice_number'],
            'count_items'      => $deleted['count_items'],
            'deleted'          => $deleted
          ]
        );

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

        log_notice('Confirm this order was returned! Order with tracking number was deleted', $deleted);

        export_wc_return_order($invoice_number);

        continue;
      }

      export_gd_delete_invoice($deleted['invoice_number']);

      $is_canceled = ($deleted['count_filled'] > 0 OR is_webform($deleted));

      //[NULL, 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
      if ($is_canceled)
        export_wc_cancel_order($deleted['invoice_number'], "update_orders_cp: cp order canceled $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source] ".json_encode($deleted));
      else
        export_wc_delete_order($deleted['invoice_number'], "update_orders_cp: cp order deleted $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source] ".json_encode($deleted));

      export_cp_remove_items($deleted['invoice_number']);

      $patient = load_full_patient($deleted, $mysql, true);  //Cannot load order because it was already deleted in changes_orders_cp
      $groups  = group_drugs($patient, $mysql);

      log_info('update_orders_cp deleted: unpending all items', ['deleted' => $deleted, 'groups' => $groups, 'patient' => $patient]);
      foreach($patient as $item) {
        v2_unpend_item(array_merge($item, $deleted), $mysql);
      }

      $replacement = get_current_orders($mysql, ['patient_id_cp' => $deleted['patient_id_cp']]);

      if ($replacement) {
        log_warning('update_orders_cp deleted: their appears to be a replacement', ['deleted' => $deleted, 'replacement' => $replacement, 'groups' => $groups, 'patient' => $patient]);
        continue;
      }

      //We should be able to delete wc-confirm-* from CP queue without triggering an order cancel notice
      if ($deleted['count_filled'] == 0 AND $deleted['count_nofill'] == 0) { //count_items may already be 0 on a deleted order that had items e.g 33840
        log_warning("update_orders_cp deleted: no_rx_notice count_filled == 0 AND count_nofill == 0", ['deleted' => $deleted, 'groups' => $groups, 'patient' => $patient]);
        no_rx_notice($deleted, $groups);
        continue;
      }

      if ($is_canceled) {
        log_warning("update_orders_cp deleted: order_canceled_notice is this right?", ['deleted' => $deleted, 'groups' => $groups, 'patient' => $patient]);
        order_canceled_notice($deleted, $groups); //We passed in $deleted because there is not $order to make $groups
        continue;
      }

    }
    log_timer('orders-cp-deleted', $loop_timer, $count_deleted);


    //If just updated we need to
    //  - see which fields changed
    //  - think about what needs to be updated based on changes
    $loop_timer = microtime(true);
    foreach ($changes['updated'] as $i => $updated) {

        SirumLog::$subroutine_id = "orders-cp-updated-".sha1(serialize($updated));

        SirumLog::debug(
          "Carepoint Order $updated[invoice_number] has been updated",
          [
            'source'         => 'CarePoint',
            'event'          => 'updated',
            'invoice_number' => $updated['invoice_number'],
            'type'           => 'orders',
            'updated'        => $updated
          ]
        );

        $changed_fields  = changed_fields($updated);
        $stage_change_cp = $updated['order_stage_cp'] != $updated['old_order_stage_cp'];

        log_notice("Updated Orders Cp: $updated[invoice_number] ".($i+1)." of ".count($changes['updated']), $changed_fields);

        $order  = load_full_order($updated, $mysql);
        $groups = group_drugs($order, $mysql);

        if (!$order) {
          log_error("Updated Order Missing", $order);
          continue;
        }

        SirumLog::debug(
          "Order found for updated order",
          [
            'invoice_number'     => $order[0]['invoice_number'],
            'order'              => $order,
            'updated'            => $updated,
            'order_date_shipped' => $updated['order_date_shipped'],
            'stage_change_cp'    => $stage_change_cp
          ]
        );

        if ($stage_change_cp AND $updated['order_date_shipped']) {
          log_notice("Updated Order Shipped Started", $order);
          export_v2_unpend_order($order, $mysql);
          export_wc_update_order_status($order); //Update status from prepare to shipped
          export_wc_update_order_metadata($order);
          send_shipped_order_communications($groups);
          continue;
        }

        if ($stage_change_cp AND $updated['order_date_dispensed']) {
          $reason = "update_orders_cp updated: Updated Order Dispensed ".$updated['invoice_number'];
          $order = helper_update_payment($order, $reason, $mysql);
          $order = export_gd_update_invoice($order, $reason, $mysql);
          $order = export_gd_publish_invoice($order, $mysql);
          export_gd_print_invoice($order);
          send_dispensed_order_communications($groups);
          log_notice($reason, $order);
          continue;
        }

        //We should be able to delete wc-confirm-* from CP queue without triggering an order cancel notice
        if ($order[0]['count_filled'] == 0 AND $order[0]['count_nofill'] == 0) { //count_items may already be 0 on a deleted order that had items e.g 33840
          log_warning("update_orders_cp updated: no_rx_notice count_filled == 0 AND count_nofill == 0", ['updated' => $updated, 'groups' => $groups]);
          no_rx_notice($updated, $groups);
          continue;
        }

        //Patient communication that we are cancelling their order examples include:
        //NEEDS FORM, ORDER HOLD WAITING FOR RXS, TRANSFER OUT OF ALL ITEMS, ACTION PATIENT OFF AUTOFILL
        //count_items instead of count_filled because it might be a manually added item, that we are not filling but that the pharmacist is using as a placeholder/reminder e.g 54732
        if ($order[0]['count_items'] == 0 AND $order[0]['count_filled'] == 0 AND $order[0]['count_to_add'] == 0 AND ! is_webform_transfer($order[0])) {

          SirumLog::alert(
            'update_orders_cp: updated. no drugs to fill. remove order '.$order[0]['invoice_number'].'?',
            [
              'invoice_number' => $order[0]['invoice_number'],
              'count_filled'   => $order[0]['count_filled'],
              'count_items'    => $order[0]['count_items'],
              'order'          => $order
            ]
          );

          order_canceled_notice($updated, $groups); //We passed in $deleted because there is not $order to make $groups

          //TODO Necessary to Remove/Cancel WC Order Here?

          //TODO Why do we need to explicitly unpend?  Deleting an order in CP should trigger the deleted loop on next run, which should unpend
          //But it seemed that this didn't happen for Order 53684
          export_v2_unpend_order($order, $mysql);
          export_cp_remove_order($order[0]['invoice_number'], 'Updated Empty');
          continue;
        }

        //Address Changes
        //Stage Change
        //Order_Source Change (now that we overwrite when saving webform)
        log_notice("update_orders_cp updated: no action taken $updated[invoice_number]", [$order, $updated, $changed_fields]);
    }
    log_timer('orders-cp-updated', $loop_timer, $count_updated);

    SirumLog::resetSubroutineId();
}
