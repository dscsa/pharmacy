<?php

function cp_to_wc_key($key) {

  $cp_to_wc = [
    'patient_zip' => 'billing_postcode',
    'patient_state' => 'billing_state',
    'patient_city' => 'billing_city',
    'patient_address2' => 'billing_address_2',
    'patient_address1' => 'billing_address_1',
    'payment_coupon' => 'coupon',
    'tracking_coupon' => 'coupon',
    'phone2' => 'billing_phone',
    'phone1' => 'phone',
    'patient_note' => 'medications_other'
  ];

  return isset($cp_to_wc[$key]) ? $cp_to_wc[$key] : $key;
}

function upsert_patient_wc($mysql, $user_id, $meta_key, $meta_value) {

  $wc_key = cp_to_wc_key($meta_key);
  $wc_val = is_null($meta_value) ? 'NULL' : "'".escape_db_values($meta_value)."'";

  $select = "SELECT * FROM wp_usermeta WHERE user_id = $user_id AND meta_key = '$wc_key'";

  $exists = $mysql->run($select);

  if (isset($exists[0][0])) {
    $upsert = "UPDATE wp_usermeta SET meta_value = $wc_val WHERE user_id = $user_id AND meta_key = '$wc_key'";
  } else {
    $upsert = "INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES (NULL, $user_id, '$wc_key', $wc_val)";
  }

  $mysql->run($upsert);
}

/**
 * Create the association between the wp and the cp patient
 * @param  Mysql_Wc $mysql         The GP Mysql Connection
 * @param  array    $patient       The patient data
 * @param  int      $patient_id_cp The CP id for the patient
 * @return void
 */
function match_patient_wc($mysql, $patient, $patient_id_cp) {
  // Update the patientes table
  $mysql->run(
      "UPDATE
          gp_patients
        SET
          patient_id_wc = {$patient['patient_id_wc']}
        WHERE
          patient_id_wc IS NULL AND
          patient_id_cp = '{$patient_id_cp}'
  ");

  // Insert the patient_id_cp if it deosnt' already exist
  upsert_patient_wc(
      $mysql,
      $patient['patient_id_wc'],
      'patient_id_cp',
      $patient_id_cp
  );

  log_notice("update_patients_wc: matched $patient[first_name] $patient[last_name]");
}

function find_patient_wc($mysql, $patient, $table = 'gp_patients') {
  $first_name_prefix = explode(' ', $patient['first_name']);
  $last_name_prefix  = explode(' ', $patient['last_name']);
  $first_name_prefix = escape_db_values(substr(array_shift($first_name_prefix), 0, 3));
  $last_name_prefix  = escape_db_values(array_pop($last_name_prefix));

  $sql = "
    SELECT *
    FROM $table
    WHERE
      first_name LIKE '$first_name_prefix%' AND
      REPLACE(last_name, '*', '') LIKE '%$last_name_prefix' AND
      birth_date = '$patient[birth_date]'
  ";

  log_info('update_patients_wc: finding', [$sql, $patient]);

  return $mysql->run($sql)[0];
}

function update_wc_phone1($mysql, $patient_id_wc, $phone1) {
  if ( ! $patient_id_wc) return;
  $mysql->run("UPDATE gp_patients_wc SET phone1 = ".($phone1 ?: 'NULL')." WHERE patient_id_wc = $patient_id_wc");
  return upsert_patient_wc($mysql, $patient_id_wc, 'phone',  $phone1);
}

function update_wc_phone2($mysql, $patient_id_wc, $phone2) {
  if ( ! $patient_id_wc) return;
  $mysql->run("UPDATE gp_patients_wc SET phone2 = ".($phone2 ?: 'NULL')." WHERE patient_id_wc = $patient_id_wc");
  return upsert_patient_wc($mysql, $patient_id_wc, 'billing_phone', $phone2);
}
