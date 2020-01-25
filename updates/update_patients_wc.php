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
    '$created_mismatched' => $created_mismatched,
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

  foreach($changes['updated'] as $i => $updated) {

    $changed = changed_fields($updated);

    $cp_to_wc = [
      'patient_zip' => 'billing_postcode',
      'patient_state' => 'billing_state',
      'patient_city' => 'billing_city',
      'patient_address2' => 'billing_address_2',
      'patient_address1' => 'billing_address_1',
      'payment_coupon' => 'coupon',
      'tracking_coupon' => 'coupon',
      'pharmacy_name' => 'backup_pharmacy',
      'pharmacy_npi' => 'backup_pharmacy',
      'pharmacy_fax' => 'backup_pharmacy',
      'pharmacy_phone' => 'backup_pharmacy',
      'pharmacy_address' => 'backup_pharmacy',
      'phone2' => 'billing_phone',
      'phone1' => 'phone',
      'patient_note' => 'medications_other'
    ];

    $set_patients = [];
    $set_usermeta = [];
    foreach ($changed as $key => $val) {

      $old_val = $updated['old_'.$key];
      $new_val = $updated[$key];

      if ($new_val AND ! $old_val) {

        if ($key == 'phone2' AND $updated['phone2'] == $updated['phone1'])
          continue;

        $set_patients[] = "$key = '$new_val'";
      }

      if ( ! $new_val AND $old_val) {

        $wc_key = isset($cp_to_wc[$key]) ? $cp_to_wc[$key] : $key;
        $wc_val = $old_val;

        if ($wc_key == 'medications_other') continue;

        if (substr($wc_key, 8) == 'billing_') {
          $sql = "UPDATE wp_usermeta SET meta_value = $old_val WHERE user_id = $updated[patient_id_wc] AND meta_key = '$wc_key'";
          echo "
          $sql";
          continue;
          //$mysql->run($sql);
        }

        if ($wc_key == 'backup_pharmacy')
          $wc_val = json_encode([
            'name' => $updated['old_pharmacy_name'],
            'npi' => $updated['old_pharmacy_npi'],
            'fax' => $updated['old_pharmacy_fax'],
            'phone' => $updated['old_pharmacy_phone'],
            'street' => $updated['old_pharmacy_address']
          ]);


        $set_usermeta[$wc_key] = "(NULL, $updated[patient_id_wc], '$wc_key',  '$wc_val')";
      }
    }

    $set_patients = implode(', ', $set_patients);
    $set_usermeta = implode(', ', $set_usermeta);

    echo "

    ".json_encode($changed, JSON_PRETTY_PRINT);

    //if ($set_patients)
    //  log_error("update_patients_wc: UPDATE cppat SET $set_patients WHERE pat_id = $updated[patient_id_cp]");

    if ( ! empty($changed['last_name'])) {
      $sql = "UPDATE wp_usermeta SET meta_value = UPPER('$updated[last_name]') WHERE user_id = $updated[patient_id_wc] AND meta_key = 'last_name'";
      echo "
      $sql";
      //if ($i < 10)
      //  $mysql->run($sql);
    }
    if ($set_usermeta)
      $sql = "INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES $set_usermeta";
      echo "
      $sql";
      //if ($i < 10)
      //  $mysql->run($sql);
  }
}
