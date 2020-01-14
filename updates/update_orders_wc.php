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

    if ($created['invoice_number'] > 25000) {
      $notices[] = ["Order created in WC", $created];
    }

  }

  //This captures 2 USE CASES:
  //1) An order is in WC and CP but then is deleted in WC, probably because wp-admin deleted it (look for Update with order_stage_wc == 'trash')
  //2) An order is in CP but not in (never added to) WC, probably because of a tech bug.
  foreach($changes['deleted'] as $deleted) {

    if ($deleted['order_stage_wc'] == 'trash') {

      if ($deleted['invoice_number'] < 25000) {

        if ($deleted['invoice_number'] = 19901) {
          $order = get_full_order($deleted, $mysql);
          $order = helper_update_payment($order, $mysql);

          $group = group_drugs($order, $mysql);

          $order[0]['count_filled'] = $group['COUNT_FILLED'];
          $order[0]['count_nofill'] = $group['COUNT_NOFILL'];

          export_wc_create_order($order);
          export_gd_publish_invoice($order)
        }
        $notices[] = ["Adding Order to WC", $deleted];

      } else
        $notices[] = ["Order deleted in WC", $deleted];
    }

  }

  foreach($changes['updated'] as $updated) {

    if ($updated['order_stage_wc'] == 'trash') {
      $notices[] = ["Order trashed in WC", $updated];
    } else {
      $notices[] = ["Order updated in WC", $updated];
    }

  }

  log_notice("update_orders_wc notices", $notices);

}
