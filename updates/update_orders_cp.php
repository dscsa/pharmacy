<?php

require_once 'helpers/helper_full_order.php';
require_once 'helpers/helper_payment.php';
require_once 'helpers/helper_syncing.php';
require_once 'helpers/helper_communications.php';
require_once 'exports/export_wc_orders.php';
require_once 'exports/export_cp_orders.php';

use Sirum\Logging\SirumLog;

function update_orders_cp($changes) {
  global $global_timer_details;
  $section_timer_details = [];

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
      if ($item['qty_dispensed_default'] != $item['qty_dispensed_actual'] OR (( ! is_null($item['refills_dispensed_actual']) AND $item['refills_dispensed_default']+0) != $item['refills_dispensed_actual']))
        $qty_changes[] = "rx:$item[rx_number] qty:$item[qty_dispensed_default] >>> $item[qty_dispensed_actual] days:$item[days_dispensed_default] >>> $item[days_dispensed_actual] refills:$item[refills_dispensed_default] >>> $item[refills_dispensed_actual] item:".json_encode($item);

      //! $updated['order_date_dispensed'] otherwise triggered twice, once one stage: Printed/Processed and again on stage:Dispensed
      $sig_qty_per_day_actual = $item['days_dispensed_actual'] ? round($item['qty_dispensed_actual']/$item['days_dispensed_actual'], 3) : 'NULL';
      $mysql->run("
        UPDATE gp_rxs_single SET sig_qty_per_day_actual = $sig_qty_per_day_actual WHERE rx_number = $item[rx_number]
      ");

      if ($item['days_dispensed_actual']) {
        if ($item['refills_dispensed'] AND ! $item['qty_left'] AND ($item['days_dispensed_actual'] > DAYS_MAX OR $item['days_dispensed_actual'] < DAYS_MIN)) {
          log_error("check days dispensed is not within limits and it's not out of refills: ".DAYS_MIN." < $item[days_dispensed_actual] < ".DAYS_MAX, $item);
        }

        if ( ! $sig_qty_per_day_actual OR $item['sig_qty_per_day_default']*2 < $sig_qty_per_day_actual OR $item['sig_qty_per_day_default']/2 > $sig_qty_per_day_actual) {
          log_error("sig parsing error Updating to Actual Qty_Per_Day '$item[sig_actual]' $item[sig_qty_per_day_default] (default) != $sig_qty_per_day_actual $item[qty_dispensed_actual]/$item[days_dispensed_actual] (actual)", $item);
        }
      }
    }

    log_notice("update_order_cp detect_dispensing_changes", ['order' => $order, 'day_changes' => $day_changes, 'qty_changes' => $qty_changes]);
    return ['day_changes' => $day_changes, 'qty_changes' => $qty_changes];
  }

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

        //TODO Add Webform Transfer [w/ Note] here as well
        if ($created['order_status'] == "Surescripts Authorization Denied") {
          SirumLog::error(
            "Surescripts Authorization Denied. Created. What to do here?  Resend?  Retrieve Reason?  Delete Order?",
            [
              'invoice_number' => $created['invoice_number'],
              'created' => $created,
              'source'  => 'CarePoint',
              'type'    => 'orders',
              'event'   => 'created'
            ]
          );

          continue; //Not sure what we should do here.  Process them?  Patient communication?
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

        if ($order[0]['order_status'] == "Surescripts Authorization Approved")
          SirumLog::error(
            "Surescripts Authorization Approved. Created.  What to do here?  Keep Order? Delete Order? Depends on Autofill settings?",
            [
              'invoice_number'   => $order[0]['invoice_number'],
              'count_items'      => count($order)." / ".@$order['count_items'],
              'patient_autofill' => $order[0]['patient_autofill'],
              'rx_autofill'      => $order[0]['rx_autofill'],
              'order'            => $order
            ]
          );

        if ($order[0]['order_stage_wc'] == 'wc-processing') {
            SirumLog::debug(
                'Problem: cp order wc-processing created',
                [
                  'invoice_number' => $order[0]['invoice_number'],
                  'order'          => $order
                ]
            );
        }

        if ($order[0]['order_date_dispensed']) {
            export_wc_create_order($order, "update_orders_cp: dispened/shipped order being readded");
            export_gd_publish_invoice($order, $mysql);
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
        if ($order[0]['count_filled'] == 0) {

          SirumLog::debug(
            'update_orders_cp: created. no drugs to fill. removing order',
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

          export_cp_remove_order($order[0]['invoice_number']);
          continue;
        }

        if ($order[0]['count_items_to_remove'] > 0 OR $order[0]['count_items_to_add'] > 0) {

          SirumLog::debug(
            'update_orders_cp: created. skipping order because items still need to be removed/added',
            [
              'invoice_number'        => $order[0]['invoice_number'],
              'count_filled'          => $order[0]['count_filled'],
              'count_items'           => $order[0]['count_items'],
              'count_items_to_remove' => $order[0]['count_items_to_remove'],
              'count_items_to_add'    => $order[0]['count_items_to_add'],
              'order'                 => $order
            ]
          );

          //Force created loop to run again so that patient gets "Order Created Communication" and not just an update one
           $mysql->run("
            DELETE gp_orders
            FROM gp_orders
            WHERE invoice_number = {$order[0]['invoice_number']}
          ");

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

        if (!$order[0]['pharmacy_name']) {
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

        if ($created['order_date_dispensed']) { //Can't test for rx_message_key == 'ACTION NEEDS FORM' because other messages can take precedence
          export_gd_publish_invoice($order, $mysql);
          export_gd_print_invoice($order);

          SirumLog::notice(
            "update_orders_cp Created Order is being readded (and invoice " .
              "recreated) even though already dispensed.  Was it deleted on purpose?",
            [
              'invoice_number' => $order[0]['invoice_number'],
              'order' => $order
            ]
          );

          continue;
        }

        //This is not necessary if order was created by webform, which then created the order in Guardian
        //"order_source": "Webform eRX/Transfer/Refill [w/ Note]"
        if (strpos($order[0]['order_source'], 'Webform') === false) {
          export_wc_create_order($order, "update_orders_cp: created");
          SirumLog::debug(
            "Created & Pended Order",
            [
              'invoice_number' => $order[0]['invoice_number'],
              'order'  => $order,
              'synced' => $synced,
              'groups' => $groups
            ]
          );
        } else {
          SirumLog::notice(
            "Order creation skipped because source not Webform",
            [
              'invoice_number' => $order[0]['invoice_number'],
              'source'         => $order[0]['order_source'],
              'order'          => $order,
              'groups'         => $groups,
              'synced'         => $synced
            ]
          );
        }

        if ( ! $groups['COUNT_FILLED']) {
          order_hold_notice($groups, true);
          SirumLog::debug(
            "update_orders_cp: Order Hold hopefully due to 'NO ACTION MISSING GSN' otherwise should have been deleted with sync code above",
            [
              'invoice_number' => $order[0]['invoice_number'],
              'order' => $order,
              'groups' => $groups
            ]
          );
        } else {
          send_created_order_communications($groups);
        }
        //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
    } // END created loop
    $section_timer_details['created_orders'] = ceil(microtime(true) - $loop_timer);

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
          'update_orders_cp: carepoint order has been deleted',
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

      //[NULL, 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
      if ($deleted['count_filled'] > 0 OR in_array($deleted['order_source'], ['Webform eRx', 'Webform Transfer', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']))
        export_wc_cancel_order($deleted['invoice_number'], "update_orders_cp: cp order canceled $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source] ".json_encode($deleted));
      else
        export_wc_delete_order($deleted['invoice_number'], "update_orders_cp: cp order deleted $deleted[invoice_number] $deleted[order_stage_cp] $deleted[order_stage_wc] $deleted[order_source] ".json_encode($deleted));

      export_v2_unpend_order([$deleted], $mysql);

      export_cp_remove_items($deleted['invoice_number']);

      $replacement = get_current_orders($mysql, ['patient_id_cp' => $deleted['patient_id_cp']]);

      if ($replacement) {
        log_error('order_canceled_notice BUT their appears to be a replacement', ['deleted' => $deleted, 'sql' => $sql, 'replacement' => $replacement]);
        continue;
      }

      //We should be able to delete wc-confirm-* from CP queue without triggering an order cancel notice
      if ( ! $deleted['count_filled'] AND ! $deleted['count_nofill']) { //count_items may already be 0 on a deleted order that had items e.g 33840
        no_rx_notice($deleted, $patient);
        log_error("update_orders_cp deleted: count_filled == 0 AND count_nofill == 0 so calling no_rx_notice() rather than order_canceled_notice()", $deleted);
        continue;
      }

      order_canceled_notice($deleted, $patient); //We passed in $deleted because there is not $order to make $groups
    }
    $section_timer_details['deleted_orders'] = ceil(microtime(true) - $loop_timer);

    //If just updated we need to
    //  - see which fields changed
    //  - think about what needs to be updated based on changes
    $loop_timer = microtime(true);
    foreach ($changes['updated'] as $i => $updated) {

        SirumLog::$subroutine_id = "orders-cp-updated-".sha1(serialize($updated));

        SirumLog::debug(
          'Carepoint Order has been updated',
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

        SirumLog::debug(
          "get_full_order: Carepoint Order Updated",
          ['updated' => $updated]
        );

        $order = load_full_order($updated, $mysql);

        if (!$order) {
          SirumLog::notice(
            "Order not found",
            [
              'order'          => $order,
              'updated'        => $updated
            ]
          );
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
          $groups = group_drugs($order, $mysql);
          export_v2_unpend_order($order, $mysql);
          export_wc_update_order_status($order); //Update status from prepare to shipped
          export_wc_update_order_metadata($order);
          send_shipped_order_communications($groups);
          continue;
        }

        if ($stage_change_cp AND $updated['order_date_dispensed']) {

            $dispensing_changes = detect_dispensing_changes($order);

            if ($dispensing_changes['day_changes']) {
              //Updates invoice with new days/price/qty/refills.
              $order = helper_update_payment($order, "update_orders_cp: updated - dispensing day changes ".implode(', ', $dispensing_changes['day_changes']), $mysql);
              export_wc_update_order($order); //Price will also have changed

            } elseif ($dispensing_changes['qty_changes']) {
              //Updates invoice with new qty/refills.  Prices should not have changed so no need to update WC
              $order = export_gd_update_invoice($order, "update_orders_cp: updated - dispensing qty changes ".implode(', ', $dispensing_changes['qty_changes']), $mysql);
            }

            $groups = group_drugs($order, $mysql);

            export_gd_publish_invoice($order, $mysql);
            export_gd_print_invoice($order);
            send_dispensed_order_communications($groups);
            log_notice("update_orders_cp updated: Updated Order Dispensed", $order);
            continue;
        }

        //Patient communication that we are cancelling their order examples include:
        //NEEDS FORM, ORDER HOLD WAITING FOR RXS, TRANSFER OUT OF ALL ITEMS, ACTION PATIENT OFF AUTOFILL
        if ($order[0]['count_filled'] == 0) {

          SirumLog::alert(
            'update_orders_cp: updated. no drugs to fill. remove order?',
            [
              'invoice_number' => $order[0]['invoice_number'],
              'count_filled'   => $order[0]['count_filled'],
              'count_items'    => $order[0]['count_items'],
              'order'          => $order
            ]
          );

          //$groups = group_drugs($order, $mysql);
          //order_hold_notice($groups);

          //export_cp_remove_order($updated['invoice_number']);
          continue;
        }

        if ($order[0]['count_items_to_remove'] > 0 OR $order[0]['count_items_to_add'] > 0) {

          SirumLog::debug(
            'update_orders_cp: updated. skipping order because items still need to be removed/added',
            [
              'invoice_number'        => $order[0]['invoice_number'],
              'count_filled'          => $order[0]['count_filled'],
              'count_items'           => $order[0]['count_items'],
              'count_items_to_remove' => $order[0]['count_items_to_remove'],
              'count_items_to_add'    => $order[0]['count_items_to_add'],
              'order'                 => $order
            ]
          );

          //On the next run the count_items will be updated to count_filled and this UPDATE LOOP will run again so no need to run it now
          continue;
        }

        if ($updated['count_items'] != $updated['old_count_items']) {

          $groups = group_drugs($order, $mysql);
          send_updated_order_communications($groups, $changed_fields);

          continue;
        }

        //Address Changes
        //Stage Change
        //Order_Source Change (now that we overwrite when saving webform)
        log_notice("update_orders_cp updated: no action taken $updated[invoice_number]", [$order, $updated, $changed_fields]);
    }
    $section_timer_details['updated_orders'] = ceil(microtime(true) - $loop_timer);
    $global_timer_details['update_orders_cp_loops'] = $section_timer_details;
    SirumLog::resetSubroutineId();
}
