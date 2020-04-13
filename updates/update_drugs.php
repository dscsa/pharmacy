<?php

require_once 'changes/changes_to_drugs.php';

function update_drugs() {

  $changes = changes_to_drugs("gp_drugs_v2");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_drugs: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());


  foreach($changes['updated'] as $i => $updated) {

    if ($updated['drug_ordered'] && ! $updated['old_drug_ordered'])
      log_error("new drug ordered", $updated);

    if ( ! $updated['drug_ordered'] && $updated['old_drug_ordered'])
      log_error("drug stopped being ordered", $updated);

    if ($updated['drug_gsns'] != $updated['old_drug_gsns'])
      log_error("drug gsns changed", $updated);

  }


  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
