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
  $mssql = new Mssql_Cp();

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

  function upsert_patient_cp($mssql, $stored_procedure, $live = false) {
    echo "
    live:$live EXEC $stored_procedure";

    if ($live) $mssql->run("EXEC $stored_procedure");
  }

  $created_mismatched = 0;
  $created_matched = 0;
  $created_needs_form = 0;
  $created_new_to_cp = 0;

  foreach($changes['created'] as $created) {

    $first_name_prefix = explode(' ', $created['first_name']);
    $last_name_prefix  = explode(' ', $created['last_name']);
    $first_name_prefix = substr(array_shift($first_name_prefix), 0, 3);
    $last_name_prefix  = array_pop($last_name_prefix);

    $sql = "
      SELECT *
      FROM gp_patients
      WHERE
        first_name LIKE '".$first_name_prefix."%' AND
        REPLACE(REPLACE(last_name, '*', ''), '\'', '') LIKE '%$last_name_prefix' AND
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
          patient_id_wc IS NULL AND
          patient_id_cp = '".$patient[0]['patient_id_cp']."'
      ";

      $mysql->run($sql2);
      $mysql->run($sql3);

      log_notice('update_patients_wc: matched', [$sql2, $sql3, $patient[0]]);
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

    $changed
      ? log_error("update_patients_wc: updated changed cp:$updated[patient_id_cp] wc:$updated[patient_id_wc]", $changed)
      : log_error("update_patients_wc: updated no change? cp:$updated[patient_id_cp] wc:$updated[patient_id_wc]", $updated);

    if ( ! $updated['email'] AND $updated['old_email']) {
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'email', $update['old_email']);
    } else if ($updated['email'] !== $updated['old_email']) {
      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatEmail '$updated[patient_id_cp]', '$updated[email]'");
    }

    if (
        ( ! $updated['patient_address1'] AND $updated['old_patient_address1']) OR
        ( ! $updated['patient_address2'] AND $updated['old_patient_address2']) OR
        ( ! $updated['patient_city'] AND $updated['old_patient_city']) OR
        ( ! $updated['patient_state'] AND $updated['old_patient_state']) OR
        ( ! $updated['patient_zip'] AND $updated['old_patient_zip'])
    ) {
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_address1', $update['old_patient_address1']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_address2', $update['old_patient_address2']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_city', $update['old_patient_city']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_state', $update['old_patient_state']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_zip', $update['old_patient_zip']);
    } else if (
        $updated['patient_address1'] !== $updated['old_patient_address1'] OR
        $updated['patient_address2'] !== $updated['old_patient_address2'] OR
        $updated['patient_city'] !== $updated['old_patient_city'] OR
        $updated['patient_state'] !== $updated['old_patient_state'] OR
        $updated['patient_zip'] !== $updated['old_patient_zip']
    ) {

      $address1 = $updated['patient_address1'];

      if ($updated['patient_state'] != 'GA')
        $address1 = "WARNING NON-GEORGIA ADDRESS: $address1";

      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatHomeAddr '$updated[patient_id_wc]', '$address1', $updated[patient_address2], NULL, '$updated[patient_city]', '$updated[patient_state]', '$updated[patient_zip]', 'US'");
    }

    //NOTE: Different logic here. Deleting in CP should save back into WC
    if (
        ($updated['payment_coupon'] AND ! $updated['old_payment_coupon']) OR
        ($updated['tracking_coupon'] AND ! $updated['old_tracking_coupon'])
    ) {
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'coupon', $updated['old_payment_coupon'] ?: $updated['old_tracking_coupon']);
    } else if (
            //$updated['payment_card_last4'] !== $updated['old_payment_card_last4'] OR
            //$updated['payment_card_date_expired'] !== $updated['old_payment_card_date_expired'] OR
            //$updated['payment_card_type'] !== $updated['old_payment_card_type'] OR
            $updated['payment_coupon'] !== $updated['old_payment_coupon'] OR //Still allow for deleteing coupons in CP
            $updated['tracking_coupon'] !== $updated['old_tracking_coupon'] //Still allow for deleteing coupons in CP
    ) {
      $user_def4 = "$updated[payment_card_last4],$updated[payment_card_date_expired],$updated[payment_card_type],".($updated['payment_coupon'] ?: $updated['tracking_coupon']);
      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '4', '$user_def4'");
    }

    if (strlen($updated['phone1']) < 10 AND strlen($updated['old_phone1']) >= 10) {
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'phone1', $updated['old_phone1']);
    } else if ($updated['phone1'] !== $updated['old_phone1']) {
      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone1]'");
    }

    if ($updated['phone2'] AND $updated['phone2'] == $updated['phone1']) {
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'phone2', NULL, true);
    } else if ($updated['old_phone2'] AND $updated['old_phone2'] == $updated['old_phone1']) {
      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '', 9", true);
    } else if (strlen($updated['phone2']) < 10 AND strlen($updated['old_phone2']) >= 10) {
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'phone2', $updated['old_phone2'], true);
    } else if ($updated['phone2'] !== $updated['old_phone2']) {
      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone2]', 9", true);
    }

    //If pharmacy name changes then trust WC over CP
    if ($updated['pharmacy_name'] AND $updated['pharmacy_name'] !== $updated['old_pharmacy_name']) {

      $user_def1 = str_replace("'", "''", $updated['pharmacy_name']);
      $user_def2 = substr("$updated[pharmacy_npi],$updated[pharmacy_fax],$updated[pharmacy_phone],$updated[pharmacy_address]", 0, 50);

      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '1', '$user_def1'");
      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '2', '$user_def2'");
    } else if ( //If pharmacy name is the same trust CP data over WC data so always update WC
        $updated['pharmacy_npi'] !== $updated['old_pharmacy_npi'] OR
        $updated['pharmacy_fax'] !== $updated['old_pharmacy_fax'] OR
        $updated['pharmacy_phone'] !== $updated['old_pharmacy_phone'] //OR
        //$updated['pharmacy_address'] !== $updated['old_pharmacy_address'] // We only save a partial address in CP so will always differ
    ) {

      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'backup_pharmacy', json_encode([
        'name' => str_replace("'", "''", $updated['old_pharmacy_name']),
        'npi' => $updated['old_pharmacy_npi'],
        'fax' => $updated['old_pharmacy_fax'],
        'phone' => $updated['old_pharmacy_phone'],
        'street' => $updated['pharmacy_address'] // old_pharamcy address is not populated since We only save a partial address in CP so will always differ
      ]), true);
    }

    if ($updated['payment_method_default'] AND ! $updated['old_payment_method_default']) {

      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '3', '$updated[payment_method_default]'");

    } else if ($updated['payment_method_default'] !== $updated['old_payment_method_default']) {

      if ($updated['old_payment_method_default'] == PAYMENT_METHOD['MAIL'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['MAIL'], true);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['AUTOPAY'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['AUTOPAY'], true);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['ONLINE'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['ONLINE'], true);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['COUPON'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['COUPON'], true);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['CARD EXPIRED'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['CARD EXPIRED'], true);

      else
        echo "
        NOT SURE WHAT TO DO FOR PAYMENT METHOD $updated";
    }

    if (
        ( ! $updated['first_name'] AND $updated['old_first_name']) OR
        ( ! $updated['last_name'] AND $updated['old_last_name']) OR
        ( ! $updated['birth_date'] AND $updated['old_birth_date']) OR
        ( ! $updated['language'] AND $updated['old_language'])
    ) {

      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'first_name', $updated['old_first_name']);
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'last_name', $updated['old_last_name']);
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'birth_date', $updated['old_birth_date']);
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'language', $updated['old_language']);

    } else if ($updated['language'] !== $updated['language']) {
      upsert_patient_cp($mssql, "SirumWeb_AddUpdatePatient '$updated[first_name]', '$updated[last_name]', '$updated[birth_date]', '$updated[phone1]', '$updated[language]'");
    }



    /*

    if ($SirumWeb_AddToPatientComment && $key == 'medications_other') {
      $SirumWeb_AddToPatientComment = false;
      echo "
      $updated[first_name] $updated[last_name] $updated[birth_date] $changed[$key] SirumWeb_AddToPatientComment '$updated[patient_id_cp]', '$updated[medications_other]'";
    }


    if ($SirumWeb_AddRemove_Allergies && substr($key, 0, 10) == 'allergies_') {
      $SirumWeb_AddRemove_Allergies = false;
      $allergies = json_encode([
        'allergies_none' => $updated['allergies_none'] ?: '',
        'allergies_aspirin' => $updated['allergies_aspirin'] ?: '',
        'allergies_amoxicillin' => $updated['allergies_amoxicillin'] ?: '',
        'allergies_azithromycin' => $updated['allergies_azithromycin'] ?: '',
        'allergies_cephalosporins' => $updated['allergies_cephalosporins'] ?: '',
        'allergies_codeine' => $updated['allergies_codeine'] ?: '',
        'allergies_erythromycin' => $updated['allergies_erythromycin'] ?: '',
        'allergies_penicillin' => $updated['allergies_penicillin'] ?: '',
        'allergies_salicylates' => $updated['allergies_salicylates'] ?: '',
        'allergies_sulfa' => $updated['allergies_sulfa'] ?: '',
        'allergies_tetracycline' => $updated['allergies_tetracycline'] ?: '',
        'allergies_other' => str_replace("'", "''", $updated['allergies_other']) ?: ''
      ]);

      $sql = "SirumWeb_AddRemove_Allergies '$updated[patient_id_cp]', '$allergies'";

      //$mssql->run($sql);

      echo "
      RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] $sql";
    }
  */
  }
}
