<?php

require_once 'changes/changes_to_drugs.php';

function update_drugs() {

  $changes = changes_to_drugs("gp_drugs_v2");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "
  update_drugs: $count_deleted deleted, $count_created created, $count_updated updated. ";

  log($message);

  mail('adam@sirum.org', "CRON: $message", $message.print_r($changes, true));

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
