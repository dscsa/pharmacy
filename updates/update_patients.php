<?php

require_once 'changes/changes_to_patients.php';

function update_patients() {

  $changes = changes_to_patients("gp_patients_cp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "
  update_patients: $count_deleted deleted, $count_created created, $count_updated updated. ";

  log_info($message);

  mail('adam@sirum.org', "CRON: $message", $message.print_r($changes, true));

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
