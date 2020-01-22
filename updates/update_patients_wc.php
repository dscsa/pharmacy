<?php

require_once 'changes/changes_to_patients_wc.php';

function update_patients_wc() {

  $changes = changes_to_patients_wc("gp_patients_wc");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_notice("update_patients_wc: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mysql = new Mysql_Wc();

  foreach($changes['created'] as $created) {

    $patient = $mysql->run("
      SELECT *
      FROM gp_patients
      WHERE
        LEFT(first_name, 3) = '$created[first_name]' AND
        last_name = REPLACE(REPLACE('$created[last_name]', '*', ''), '\'', '') AND
        birth_date = $created[birth_date]
    ")[0];

    if (isset($patient['patient_id_cp'])
      log_error('update_patients_wc: matched', [$created, $patient]);
    else
      log_error('update_patients_wc: created', [$created, $patient]);
  }

  foreach($changes['deleted'] as $deleted) {

    log_error('update_patients_wc: deleted', $deleted);

  }

  foreach($changes['updated'] as $updated) {

    log_error('update_patients_wc: updated', $updated);

  }
}
