<?php

require_once 'changes/changes_to_orders_wc.php';
require_once 'helpers/helper_full_order.php';

function update_orders_wc() {

  $changes = changes_to_orders_wc("gp_orders_wc");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_notice("update_orders_wc: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mysql = new Mysql_Wc();

  //This captures 2 USE CASES:
  //1) A user/tech created an order in WC and we need to add it to Guardian
  //2) An order is incorrectly saved in WC even though it should be gone (tech bug)
  foreach($changes['created'] as $created) {

    $new_stage = explode('-', $created['order_stage_wc']);

    if ($created['order_stage_wc'] == 'trash' OR $new_stage[1] == 'awaiting' OR $new_stage[1] == 'confirm') {

      log_info("Empty Orders are intentially not imported into Guardian", "$created[invoice_number] $created[order_stage_wc]");

    } else if (in_array($created['order_stage_wc'], [
      'wc-shipped-unpaid',
      'wc-shipped-paid',
      'wc-shipped-paid-card',
      'wc-shipped-paid-mail',
      'wc-shipped-refused',
      'wc-done-card-pay',
      'wc-done-mail-pay',
      'wc-done-clinic-pay',
      'wc-done-auto-pay'
    ])) {

      log_error("Shipped/Paid WC not in Guardian. Delete/Refund?", $created);

    //This comes from import_wc_orders so we don't need the "w/ Note" counterpart sources
    } else if (in_array($created['order_source'], ["Webform Refill", "Webform Transfer", "Webform eRx"])) {

      log_error("update_orders_wc: created Webform eRx/Refill/Transfer order that is not in CP?", $created);//.print_r($item, true);

      //log_notice("New WC Order to Add Guadian", $created);

    } else {

      log_error("update_orders_wc: created non-Webform order that is not in CP?", $created);//.print_r($item, true);

      //log_notice("Guardian Order Deleted that should be deleted from WC later in this run or already deleted", $created);
    }

  }

  //This captures 2 USE CASES:
  //1) An order is in WC and CP but then is deleted in WC, probably because wp-admin deleted it (look for Update with order_stage_wc == 'trash')
  //2) An order is in CP but not in (never added to) WC, probably because of a tech bug.
  foreach($changes['deleted'] as $deleted) {

    $order = get_full_order($deleted, $mysql);

    /* TODO Investigate if/why this is needed */
    if ( ! $order) {

      log_error("update_orders_wc: deleted WC order that is not in CP?", $deleted);

    } else if ($deleted['order_stage_wc'] == 'trash') {

      if ($deleted['tracking_number']) {
        log_notice("Shipped Order deleted from trash in WC. Why?", $deleted);

        $order = helper_update_payment($order, "update_orders_wc: deleted - trash", $mysql);

        export_wc_create_order($order, "update_orders_wc: deleted - trash");

        export_gd_publish_invoice($order);
      }

    } else if ($order[0]['item_message_key'] == 'ACTION NEEDS FORM') {
      //TODO eventually set registration comm-calendar event then delete order but right now just remove items from order
      //If all items are removed, order will not be imported from CP
      $items_to_remove = [];
      foreach($order as $item) {
        if ($item['item_date_added'] AND $item['item_added_by'] != 'MANUAL' AND ! $item['rx_dispensed_id'])
          $items_to_remove[] = $item['rx_number'];
      }

      export_cp_remove_items($item['invoice_number'], $items_to_remove);

      log_notice("update_orders_wc: RXs created an order in CP but patient has not yet registered so there is no order in WC yet", [$order, $items_to_remove]);

    } else if ($deleted['order_stage_cp'] != 'Shipped' AND $deleted['order_stage_cp'] != 'Dispensed') {

      $wc_orders = wc_get_post_id($deleted['invoice_number'], true);

      $sql = get_deleted_sql("gp_orders_wc", "gp_orders", "invoice_number");

      $gp_orders      = $mysql->run("SELECT * FROM gp_orders WHERE invoice_number = $deleted[invoice_number]");
      $gp_orders_wc   = $mysql->run("SELECT * FROM gp_orders_wc WHERE invoice_number = $deleted[invoice_number]");
      $gp_orders_cp   = $mysql->run("SELECT * FROM gp_orders_cp WHERE invoice_number = $deleted[invoice_number]");
      $deleted_orders = $mysql->run("SELECT old.* FROM gp_orders_wc as new RIGHT JOIN gp_orders as old ON old.invoice_number = new.invoice_number WHERE new.invoice_number IS NULL");

      //TODO WHAT IS GOING ON HERE?
      //Idea1:  Order had all items removed so it appeared to be deleted from CP, but when items were added back in the order 'reappeared'
      //Idea2: Failed when trying to be added to WC
      //Neither Idea1 or Idea2 seems to be the case for Order 29033
      log_error("update_orders_wc: WC Order Appears to be DELETED", [
        'order[0]' => $order[0],
        'deleted' => $deleted,
        'wc_post_id' => $wc_orders,
        'gp_orders' => $gp_orders,
        'gp_orders_wc' => $gp_orders_wc,
        'gp_orders_cp' => $gp_orders_cp,
        'sql' => $sql,
        'deleted_orders' => $deleted_orders
      ]);

      $order = helper_update_payment($order,  "update_orders_wc: deleted - 0 items", $mysql);

      export_wc_create_order($order,  "update_orders_wc: deleted - 0 items");

      export_gd_publish_invoice($order);

    } else {

      $gp_orders_wc = $mysql->run("SELECT * FROM gp_orders_wc WHERE $deleted[invoice_number]")[0];
      $gp_orders = $mysql->run("SELECT * FROM gp_orders WHERE $deleted[invoice_number]")[0];
      $wc_orders = wc_get_post_id($deleted['invoice_number']);

      $order = helper_update_payment($order, "update_orders_wc: deleted - unknown reason", $mysql);

      export_wc_create_order($order,  "update_orders_wc: deleted - unknown reason");

      export_gd_publish_invoice($order);

      log_error("Readding Order that should not have been deleted. Not sure: WC Order Deleted not through trash?", [$order[0], $gp_orders_wc, $gp_orders, $wc_orders]);
    }

  }

  foreach($changes['updated'] as $updated) {

    $changed = changed_fields($updated);

    $new_stage = explode('-', $updated['order_stage_wc']);
    $old_stage = explode('-', $updated['old_order_stage_wc']);

    if ($old_stage[0] == 'trash') {

      log_error('WC Order was removed from trash', $updated);

    } else if ($new_stage[0] == 'trash') {

      if ($old_stage[1] == 'shipped' OR $old_stage[1] == 'done' OR $old_stage[1] == 'late' OR $old_stage[1] == 'return')
        log_error("$updated[invoice_number]: Shipped Order trashed in WC. Are you sure you wanted to do this?", $updated);
      else {

        $order = get_full_order($updated, $mysql);

        if ( ! $order) {
          log_notice("$updated[invoice_number]: Non-Shipped Order trashed in WC", $updated);
          continue;
        }

        $orderdata = [
          'post_status' => 'wc-'.$order[0]['order_stage_wc']
        ];

        log_error('Why was this order trashed? It still exists in Guarduan.  Removing from trash', [
          'invoice_number' => $order[0]['invoice_number'],
          'order_stage_wc' => $order[0]['order_stage_wc'],
          'order_stage_cp' => $order[0]['order_stage_cp']
        ]);

        wc_update_order($order[0]['invoice_number'], $orderdata);
      }

    } else if (count($changed) == 1 AND $updated['order_stage_wc'] != $updated['old_order_stage_wc']) {

      if (
        ($old_stage[1] == 'confirm' AND $new_stage[1] == 'prepare') OR
        ($old_stage[1] == 'prepare' AND $new_stage[1] == 'prepare') OR //User completes webform twice then prepare-refill will overwrite prepare-erx
        ($old_stage[1] == 'prepare' AND $new_stage[1] == 'shipped') OR
        ($old_stage[1] == 'prepare' AND $new_stage[1] == 'done') OR
        ($old_stage[1] == 'shipped' AND $new_stage[1] == 'done') OR
        ($old_stage[1] == 'shipped' AND $new_stage[1] == 'late') OR
        ($old_stage[1] == 'shipped' AND $new_stage[1] == 'returned') OR
        ($old_stage[1] == 'shipped' AND $updated['order_stage_wc'] == 'wc-shipped-part-pay') OR
        ($old_stage[1] == 'shipped' AND $new_stage[1] == 'prepare') //TODO REMOVE AFTER SHOPPING SHEET DEPRECATED.  IT MARKS DISPENSED AS SHIPPED BUT HERE WE MARK IT AS PREPARE SO STATUS CAN GO BACKWARDS RIGHT NOW
      ) {
        log_notice("$updated[invoice_number]: WC Order Normal Stage Change", $changed);
      } else {
        log_error("$updated[invoice_number]: WC Order Irregular Stage Change", [$new_stage, $old_stage, $updated]);
      }

    }
    else if ( ! $updated['patient_id_wc'] AND $updated['old_patient_id_wc']) {

      //26214, 26509
      log_error("$updated[invoice_number]: WC Patient Id Removed from Order.  Likely a patient was deleted from WC that still had an order", [$changed, $updated]);


    }
    else if ($updated['patient_id_wc'] AND ! $updated['old_patient_id_wc']) {


      //26214, 26509
      log_notice("$updated[invoice_number]: WC Order was created on last run and now patient_id_wc can be added", $changed);


    } else {

      log_notice("$updated[invoice_number]: Order updated in WC", [$changed, $updated]);

    }

  }
}
