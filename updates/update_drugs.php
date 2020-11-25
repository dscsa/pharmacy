<?php

require_once 'changes/changes_to_drugs.php';

use Sirum\Logging\SirumLog;

function update_drugs() {

  $changes = changes_to_drugs("gp_drugs_v2");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  SirumLog::debug(
    'v2 Drug Changes found',
    [
      'deleted' => $changes['deleted'],
      'created' => $changes['created'],
      'updated' => $changes['updated'],
      'deleted_count' => $count_deleted,
      'created_count' => $count_created,
      'updated_count' => $count_updated
    ]
  );

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_drugs: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());


  foreach($changes['updated'] as $i => $updated) {
    SirumLog::$subroutine_id = sha1(serialize($updated));

      SirumLog::debug(
        "update_drugs: RX Updated",
        [
            'updated' => $updated,
            'source'  => 'v2',
            'type'    => 'Rx',
            'event'   => 'updated'
        ]
      );

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
