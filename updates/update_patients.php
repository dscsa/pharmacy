<?php

require_once 'changes/changes_to_patients.php';

function update_patients() {

  $changes = changes_to_patients("gp_patients_cp");

  $message = "CRON: update_patients ".print_r($changes, true);

  echo $message;

  mail('adam@sirum.org', "CRON: update_patients ", $message);

  if ( ! count($changes['deleted']+$changes['created']+$changes['updated'])) return;

  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
