<?php

require_once 'changes/changes_to_patients.php';

function update_patients() {

  $changes = changes_to_patients();

  $message = "CRON: update_patients", print_r($changes, true);

  echo $message;

  mail('adam@sirum.org', $message);

  if ( ! count($changes['upserts']+$changes['removals'])) return;

  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
