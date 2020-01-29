<?php

require_once 'changes/changes_to_orders_wc.php';
require_once 'helpers/helper_full_order.php';

function update_orders_wc() {

  $changes = changes_to_orders_wc("gp_orders_wc");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_error("update_orders_wc: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mysql = new Mysql_Wc();

  $notices = [];

  //This captures 2 USE CASES:
  //1) A user/tech created an order in WC and we need to add it to Guardian
  //2) An order is incorrectly saved in WC even though it should be gone (tech bug)
  foreach($changes['created'] as $created) {

    $stage = explode('-', $created['order_stage_wc']);

    if ($created['order_stage_wc'] == 'trash' OR $stage[1] == 'awaiting' OR $stage[1] == 'confirm') {

      //$notices[] = ["Empty Orders are intentially not imported into Guardian", $created];

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

      //$notices[] = ["Shipped/Paid WC not in Guardian. Delete/Refund?", $created];

    } else {

      $order = get_full_order($deleted, $mysql);

      if ( ! $order) continue;

      $order = helper_update_payment($order, $mysql);

      export_wc_create_order($order);
      export_gd_publish_invoice($order);

      $notices[] = ["New WC Order to Add Guadian", $created];

    }

  }

  //This captures 2 USE CASES:
  //1) An order is in WC and CP but then is deleted in WC, probably because wp-admin deleted it (look for Update with order_stage_wc == 'trash')
  //2) An order is in CP but not in (never added to) WC, probably because of a tech bug.
  foreach($changes['deleted'] as $deleted) {


    if ($deleted['order_stage_wc'] == 'trash') {

      $notices[] = ["Order deleted from trash in WC", $deleted];

      /* TODO Investigate if/why this is needed
        $order = get_full_order($deleted, $mysql);

        if ( ! $order) continue;

        $order = helper_update_payment($order, $mysql);

        export_wc_create_order($order);
        export_gd_publish_invoice($order);
      */

    } else if ($deleted['order_stage_cp'] != 'Shipped' AND $deleted['order_stage_cp'] != 'Dispensed') {

      $order = get_full_order($deleted, $mysql);

      if ( ! $order) continue;

      $order = helper_update_payment($order, $mysql);

      export_wc_create_order($order);
      export_gd_publish_invoice($order);

      //$notices[] = ["CP Created Order that has not been saved in WC yet", $deleted];

    } else {

      $order = get_full_order($deleted, $mysql);

      if ( ! $order) continue;

      $order = helper_update_payment($order, $mysql);

      export_wc_create_order($order);

      $notices[] = ["Readding Order that should not have been deleted. Not sure: WC Order Deleted not through trash?", $deleted];
    }

  }

  foreach($changes['updated'] as $updated) {

    $changed = changed_fields($updated);

    if ($updated['order_stage_wc'] == 'trash') {

      if (in_array($updated['old_order_stage_wc'], [
        'wc-shipped-unpaid',
        'wc-shipped-paid',
        'wc-shipped-paid-card',
        'wc-shipped-paid-mail',
        'wc-shipped-refused',
        'wc-done-card-pay',
        'wc-done-mail-pay',
        'wc-done-clinic-pay',
        'wc-done-auto-pay'
      ]))
        $notices[] = ["Shipped Order trashed in WC. Are you sure you wanted to do this?", $updated];
      else {
        $notices[] = ["Non-Shipped Order trashed in WC", $updated];

        $order = get_full_order($updated, $mysql);

        if ( ! $order) continue;

        $orderdata = [
          'post_status' => 'wc-'.$order[0]['order_stage_wc']
        ];

        log_error('reclassifying order from WC trash', [
          'invoice_number' => $order[0]['invoice_number'],
          'order_stage_wc' => $order[0]['order_stage_wc'],
          'order_stage_cp' => $order[0]['order_stage_cp']
        ]);

        wc_update_order($order[0]['invoice_number'], $orderdata);
      }

    } else if ($updated['order_stage_wc'] != $updated['old_order_stage_wc']) {

          //$notices[] = ["WC Order Stage Change", $updated];

    } else if ($updated['patient_id_wc'] AND ! $updated['old_patient_id_wc']) {


      //26214, 26509
      $notices[] = ["WC Patient Id Added to Order", [$changed, $updated]];


    } else {

      $notices[] = ["Order updated in WC", [$changed, $updated]];

    }

  }


  log_error("update_orders_wc notices", $notices);

}
