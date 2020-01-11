<?php

require_once 'changes/changes_to_orders_wc.php';

function update_orders_wc() {

  $changes = changes_to_orders_wc("gp_orders_wc");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_orders: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mssql = new Mssql_Cp();

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

  foreach($changes['created'] as $created) {

    log_notice("Created Order WC", get_defined_vars());



    //save_guardian_order($created);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  foreach($changes['deleted'] as $deleted) {

    log_notice("Deleted Order WC", get_defined_vars());

  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    log_notice("Updated Order WC", get_defined_vars());

  }
}
