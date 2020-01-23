<?php

require_once 'changes/changes_to_patients_wc.php';

function update_patients_wc() {

  $changes = changes_to_patients_wc("gp_patients_wc");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_error("update_patients_wc: $count_deleted deleted, $count_created created, $count_updated updated.");

  $mysql = new Mysql_Wc();

  $created_mismatched = 0;
  $created_matched = 0;
  $created_needs_form = 0;
  $created_new_to_cp = 0;

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

    if ( ! empty($patient[0]['patient_id_wc'])) {
      $created_mismatched++;

      log_error('update_patients_wc: mismatched patient_id_wc?', [$created, $patient[0]]);
      //No Log
    }
    else if ( ! empty($patient[0]['patient_id_cp'])) {

      $created_matched++;

      $sql2 = "
        INSERT INTO
          wp_usermeta (umeta_id, user_id, meta_key, meta_value)
        VALUES
          (NULL, '$created[patient_id_wc]', 'patient_id_cp', '".$patient[0]['patient_id_cp']."')
      ";

      $sql3 = "
        UPDATE
          gp_patients
        SET
          patient_id_wc = $created[patient_id_wc]
        WHERE
          patient_id_wc = 0 AND
          patient_id_cp = '".$patient[0]['patient_id_cp']."'
      ";

      $mysql->run($sql2);
      $mysql->run($sql3);

      log_error('update_patients_wc: matched', [$sql2, $sql3, $patient[0]]);
    }
    else if ( ! $created['pharmacy_name']) {
      $created_needs_form++;
      //Registration Started but Not Complete
    }
    else {
      $created_new_to_cp++;
      log_error('update_patients_wc: new_to_cp', [$sql, $created]);
    }
  }

  log_error('created counts', [
    '$created_upto_date' => $created_mismatched,
    '$created_matched' => $created_matched,
    '$created_needs_form' => $created_needs_form,
    '$created_new_to_cp' => $created_new_to_cp
  ]);

  foreach($changes['deleted'] as $i => $deleted) {

    //Dummy accounts that have been cleared out of WC
    if (stripos($deleted['first_name'], 'Test') !== false OR stripos($deleted['first_name'], 'User') !== false OR stripos($deleted['email'], 'user') !== false OR stripos($deleted['email'], 'test') !== false)
      continue;

    if ($deleted['patient_id_wc'])
      log_error('update_patients_wc: deleted', $deleted);
    //else
    //  log_error('update_patients_wc: never added', $deleted);

  }

  foreach($changes['updated'] as $updated) {

    $changed = changed_fields($updated);

    $set_patients = [];
    $set_usermeta = [];
    foreach ($changed as $key => $val) {
      if ($updated[$key] AND ! $updated["old_$key"])
        $set_patients[] = "$key = $updated[$key]";

      if ( ! $updated[$key] AND $updated["old_$key"])
        $set_usermeta[] = "(NULL, $changed[patient_id_wc], '$key',  '$updated[old_$key]')";
    }

    $set_patients = implode(', ', $set_patients);
    $set_usermeta = implode(', ', $set_usermeta);

    log_error("update_patients_wc: changed", $changed);

    if ($set_patients)
      log_error("update_patients_wc: UPDATE gp_patients SET $set_patients WHERE patient_id_cp = $changed[patient_id_cp]");

    if ($set_usermeta)
      log_error("update_patients_wc: INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES $set_usermeta");
  }
}
