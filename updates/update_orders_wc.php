<?php

require_once 'changes/changes_to_orders_wc.php';
require_once 'helpers/helper_full_order.php';

function update_orders_wc() {

  $changes = changes_to_orders_wc("gp_orders_wc");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_orders_wc: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();

  function save_guardian_order($patient_id_cp, $source, $is_registered, $comment = '') {

    $comment = str_replace("'", "''", $comment);
    // Categories can be found or added select * From csct_code where ct_id=5007, UPDATE csct_code SET code_num=2, code=2, user_owned_yn = 1 WHERE code_id = 100824
    // 0 Unspecified, 1 Webform Complete, 2 Webform eRx, 3 Webform Transfer, 6 Webform Refill, 7 Webform eRX with Note, 8 Webform Transfer with Note, 9 Webform Refill with Note,

    if ($source == 'pharmacy')
      $category = $comment ? 8 : 3;
    else if ($source == 'erx' AND $is_registered)
      $category = $comment ? 9 : 6;
    else if ($source == 'erx'AND ! $is_registered)
      $category = $comment ? 7 : 2;
    else
      $category = 0;

    $result = $mssql->run("SirumWeb_AddFindOrder '$patient_id_cp', '$category', '$comment'");

    return $result;
  }

  $notices = [];

  //This captures 2 USE CASES:
  //1) A user/tech created an order in WC and we need to add it to Guardian
  //2) An order is incorrectly saved in WC even though it should be gone (tech bug)
  foreach($changes['created'] as $created) {

    $stage = explode('-', $created['order_stage_wc'])[1];

    if ($stage == 'awaiting' OR $stage == 'confirming') {
      $notices[] = ["Empty Orders are intentially not imported into Guardian", $created];

    } else if (in_array($created['order_stage_wc'], [
      'wc-shipped-paid',
      'wc-shipped-paid-card',
      'wc-shipped-paid-mail',
      'wc-shipped-refused',
      'wc-done-card-pay',
      'wc-done-mail-pay',
      'wc-done-clinic-pay',
      'wc-done-auto-pay'
    ])) {

        $notices[] = ["Paid WC not in Guardian. Refund?", $created];

    } else {

      $notices[] = ["Add to Order Guadian Once We Stop Webform from Adding", $created];

    }

  }

  //This captures 2 USE CASES:
  //1) An order is in WC and CP but then is deleted in WC, probably because wp-admin deleted it (look for Update with order_stage_wc == 'trash')
  //2) An order is in CP but not in (never added to) WC, probably because of a tech bug.
  foreach($changes['deleted'] as $deleted) {

    if ($deleted['order_stage_wc'] == 'trashed') {

      $notices[] = ["Order deleted in WC", $deleted];

    } else if ($deleted['tracking_number']) {

      $notices[] = ["Historic Order never added to WC", $deleted];

    } else {

      $notices[] = ["New Order not added to WC yet", $deleted];

    }

    if (false AND $deleted['invoice_number'] < 25305) {

      $order = get_full_order($deleted, $mysql);

      if ( ! $order) continue;

      $order = helper_update_payment($order, $mysql);

      export_wc_create_order($order);
      export_gd_publish_invoice($order);
      $notices[] = ["Adding Order to WC", $order[0]['count_filled'], $deleted, $order];

      //$notices[] = ["Will Add Order to WC", $deleted];

    }

  }

  foreach($changes['updated'] as $updated) {

    if ($updated['order_stage_wc'] != $updated['old_order_stage_wc']) {
      //$notices[] = ["WC Order Stage Change", $updated];
    } else {
      $notices[] = ["Order updated in WC", $updated];
    }

  }

  log_error("update_orders_wc notices", $notices);

}
