<?php

require_once __DIR__.'/../changes/changes_to_patients';

function update_patients() {

  $changes = changes_to_patients();

  mail('adam@sirum.org', "CRON: update_patients", print_r($changes, true));

  if ( ! count($changes['upserts']+$changes['removals'])) return;

  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
