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
    'patient_note' => 'medications_other',
  ];

  return isset($cp_to_wc[$key]) ? $cp_to_wc[$key] : $key;
}

//Note we only do this because the registration was incomplete
//if completed we should move them to inactive or deceased
function wc_delete_patient($mysql, $patient_id_wc) {

  $user = "
    DELETE FROM
      wp_users
    WHERE
      ID = $patient_id_wc
  ";

  $mysql->run($user);

  $meta = "
    DELETE FROM
      wp_usermeta
    WHERE
      user_id = $patient_id_wc
  ";

  $mysql->run($meta);
}

/**
 * Update woocommerce with patient changes from CarePoint
 *
 * @param  array $patient  The data for the patient.  Needs to include firstname,
 *      lastname, birthdate, and patient_wc_id
 *
 * @return boolean
 */
function wc_update_patient($patient) {

    if (!$patient['patient_wc_id']) {
        return false;
    }

    $goodpilldb = new Sirum\Storage\Goodpill();

    $pdo = $goodpilldb->prepare(
        "UPDATE wp_users
            SET user_login = :user_login,
                user_nicename = :user_nicename,
                user_email = :user_email,
                display_name = :display_name
            WHERE id = :patient_id_wc"
    );

    $login    = "{$patient['first_name']} {$patient['last_name']} {$patient['birth_date']}";
    $nicename = "{$patient['first_name']}-{$patient['last_name']}-{$patient['birth_date']}";

    $pdo->bindParam(':user_login', $login, \PDO::PARAM_STR);
    $pdo->bindParam(':user_nicename', $nicename, \PDO::PARAM_STR);
    $pdo->bindParam(':user_email', $patient['email'], \PDO::PARAM_STR);
    $pdo->bindParam(':display_name', $login, \PDO::PARAM_STR);
    $pdo->bindParam(':patient_id_wc', $patient['patient_id_wc'], \PDO::PARAM_INT);
    $pdo->exectue();

    $mysql = ($mysql) ?: new Mysql_Wc();

    // update all the first_name meta
    foreach ([
                'first_name',
                'billing_first_name',
                'shipping_first_name'
             ] as $meta_key) {
        wc_upsert_patient_meta(
            $mysql,
            $patient['patient_id_wc'],
            $meta_key,
            $patient['first_name']
        );
    }

    foreach ([
                'last_name',
                'billing_last_name',
                'shipping_last_name'
             ] as $meta_key) {
        wc_upsert_patient_meta(
            $mysql,
            $patient['patient_id_wc'],
            $meta_key,
            $patient['last_name']
        );
    }


    wc_upsert_patient_meta(
        $mysql,
        $patient['patient_id_wc'],
        'birth_date',
        $patient['birth_date']
    );

    wc_upsert_patient_meta(
        $mysql,
        $patient['patient_id_wc'],
        'birth_date_month',
        date('m', strtotime($patient['birth_date']))
    );

    wc_upsert_patient_meta(
        $mysql,
        $patient['patient_id_wc'],
        'birth_date_day',
        date('d', strtotime($patient['birth_date']))
    );

    wc_upsert_patient_meta(
        $mysql,
        $patient['patient_id_wc'],
        'birth_date_year',
        date('Y', strtotime($patient['birth_date']))
    );
}

function wc_create_patient($mysql, $patient) {

  $insert = "
    INSERT wp_users (
      user_login,
      user_nicename,
      user_email,
      user_registered,
      display_name
    ) VALUES (
      '$patient[first_name] $patient[last_name] $patient[birth_date]',
      '$patient[first_name]-$patient[last_name]-$patient[birth_date]',
      '$patient[email]',
      '$patient[patient_date_added]',
      '$patient[first_name] $patient[last_name] $patient[birth_date]'
    )
  ";

  $mysql->run($insert);

  $user_id = $mysql->run("
    SELECT * FROM wp_users WHERE user_login = '$patient[first_name] $patient[last_name] $patient[birth_date]'
  ")[0];

  echo "\n$insert\n".print_r($user_id, true);

  foreach($patient as $key => $val) {
    wc_upsert_patient_meta($mysql, $user_id[0]['ID'], $key, $val);
  }

  update_wc_patient_active_status($mysql, $user_id[0]['ID'], null);
  update_wc_backup_pharmacy($mysql, $patient_id_wc, $patient);
}

function wc_upsert_patient_meta($mysql, $user_id, $meta_key, $meta_value) {

  $wc_key = cp_to_wc_key($meta_key);
  $wc_val = is_null($meta_value) ? 'NULL' : "'".escape_db_values($meta_value)."'";

  $select = "SELECT *
                FROM wp_usermeta
                    WHERE user_id = $user_id
                        AND meta_key = '$wc_key'";

  $exists = $mysql->run($select);

  if (isset($exists[0][0])) {
    $upsert = "UPDATE wp_usermeta SET meta_value = $wc_val WHERE user_id = $user_id AND meta_key = '$wc_key'";
  } else {
    $upsert = "INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES (NULL, $user_id, '$wc_key', $wc_val)";
  }

  $mysql->run($upsert);
}


function update_wc_backup_pharmacy($mysql, $patient_id_wc, $patient) {

  if ( ! $patient_id_wc) return;

  $wc_val = json_encode([
    'name'   => $patient['pharmacy_name'],
    'npi'    => $patient['pharmacy_npi'],
    'street' => $patient['pharmacy_address'],
    'fax'    => $patient['pharmacy_fax'],
    'phone'  => $patient['pharmacy_phone']
  ]);

  echo "\nupdate_wc_backup_pharmacy $patient_id_wc, 'backup_pharmacy',  $wc_val";

  return wc_upsert_patient_meta($mysql, $patient_id_wc, 'backup_pharmacy',  $wc_val);
}

function update_wc_patient_active_status($mysql, $patient_id_wc, $inactive) {

  if ( ! $patient_id_wc) return;

  if ($inactive == 'Inactive') {
    $wc_val = 'a:1:{s:8:"inactive";b:1;}';
  }

  else if ($inactive == 'Deceased') {
    $wc_val = 'a:1:{s:8:"deceased";b:1;}';
  }

  else {
    $wc_val = 'a:1:{s:8:"customer";b:1;}';
  }

  log_alert("update_wc_patient_active_status $inactive -> $patient_id_wc, 'wp_capabilities',  $wc_val");

  return wc_upsert_patient_meta($mysql, $patient_id_wc, 'wp_capabilities',  $wc_val);
}

function update_wc_phone1($mysql, $patient_id_wc, $phone1) {
  if ( ! $patient_id_wc) return;
  return wc_upsert_patient_meta($mysql, $patient_id_wc, 'phone',  $phone1);
}

function update_wc_phone2($mysql, $patient_id_wc, $phone2) {
  if ( ! $patient_id_wc) return;
  return wc_upsert_patient_meta($mysql, $patient_id_wc, 'billing_phone', $phone2);
}
