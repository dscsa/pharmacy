<?php


function update_patients_wc() {

  $changes = changes_to_patients_wc("gp_patients_wc");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_notice("update_patients_wc: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mysql = new Mysql_Wc();

  foreach($changes['created'] as $created) {

    log_error('update_patients_wc: created', $created);

  }

  foreach($changes['deleted'] as $deleted) {

    log_error('update_patients_wc: deleted', $deleted);

  }

  foreach($changes['updated'] as $updated) {

    log_error('update_patients_wc: updated', $updated);

  }
}
