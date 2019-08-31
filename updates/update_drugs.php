<?php

require_once 'changes/changes_to_drugs.php';

function update_drugs() {

  $changes = changes_to_drugs("gp_drugs_v2");

  $message = "CRON: update_drugs ".print_r($changes, true);

  echo $message;

  mail('adam@sirum.org', "CRON: update_drugs ", $message);

  if ( ! count($changes['deleted']+$changes['created']+$changes['updated'])) return;

  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
