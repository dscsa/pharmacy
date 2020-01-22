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

    $sql = "
      SELECT *
      FROM gp_patients
      WHERE
        first_name LIKE '".substr($created['first_name'], 0, 3)."%' AND
        REPLACE(REPLACE(last_name, '*', ''), '\'', '') = '$created[last_name]' AND
        birth_date = '$created[birth_date]'
    ";

    $patient = $mysql->run($sql)[0];

    if (isset($patient[0]['patient_id_wc'])) {
      //No Log
    }
    else if (isset($patient[0]['patient_id_cp'])) {

      $mysql->run("INSERT INTO wp_usermeta (meta_id, user_id, meta_key, meta_value) VALUES (NULL, '$created[patient_id_wc]', 'patient_id_cp', '$created[patient_id_cp]')");
      $mysql->run("UPDATE gp_patients SET patient_id_wc = $created[patient_id_wc] WHERE patient_id_wc IS NULL AND patient_id_cp = $created[patient_id_cp]");

      log_error('update_patients_wc: matched', $patient[0]);
    }
    else
      log_error('update_patients_wc: created', $created);
  }

  foreach($changes['deleted'] as $deleted) {

    log_error('update_patients_wc: deleted', $deleted);

  }

  foreach($changes['updated'] as $updated) {

    log_error('update_patients_wc: updated', $updated);

  }
}
