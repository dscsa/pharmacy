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

function upsert_patient_wc($mysql, $user_id, $meta_key, $meta_value, $live = false) {

  $wc_key = cp_to_wc_key($meta_key);
  $wc_val = is_null($meta_value) ? 'NULL' : "'".@mysql_escape_string($meta_value)."'";

  $select = "SELECT * FROM wp_usermeta WHERE user_id = $user_id AND meta_key = '$wc_key'";

  $exists = $mysql->run($select);

  if (isset($exists[0][0])) {
    $upsert = "UPDATE wp_usermeta SET meta_value = $wc_val WHERE user_id = $user_id AND meta_key = '$wc_key'";
  } else {
    $upsert = "INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES (NULL, $user_id, '$wc_key', $wc_val)";
  }


  echo "
  live:$live $upsert";

  if ($live) $mysql->run($upsert);
}

function match_patient_wc($mysql, $created, $patient_id_cp) {
  $sql1 = "
    INSERT INTO
      wp_usermeta (umeta_id, user_id, meta_key, meta_value)
    VALUES
      (NULL, '$created[patient_id_wc]', 'patient_id_cp', '$patient_id_cp')
  ";

  $sql2 = "
    UPDATE
      gp_patients
    SET
      patient_id_wc = $created[patient_id_wc]
    WHERE
      patient_id_wc IS NULL AND
      patient_id_cp = '$patient_id_cp'
  ";

  $mysql->run($sql1);
  $mysql->run($sql2);

  log_error('update_patients_wc: matched', [$sql1, $sql2]);
}

function find_patient_wc($mysql, $created) {
  $first_name_prefix = explode(' ', str_replace("'", "''", $created['first_name']));
  $last_name_prefix  = explode(' ', str_replace("'", "''", $created['last_name']));
  $first_name_prefix = substr(array_shift($first_name_prefix), 0, 3);
  $last_name_prefix  = array_pop($last_name_prefix);

  $sql = "
    SELECT *
    FROM gp_patients
    WHERE
      first_name LIKE '$first_name_prefix%' AND
      REPLACE(REPLACE(last_name, '*', ''), '\'', '') LIKE '%$last_name_prefix' AND
      birth_date = '$created[birth_date]'
  ";

  return $mysql->run($sql)[0];
}
