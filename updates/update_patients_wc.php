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

    //$set_patients = [];
    $set_usermeta = [];
    foreach ($changed as $key => $val) {

      $old_val = $updated['old_'.$key];
      $new_val = $updated[$key];

      if ($new_val AND ! $old_val) {

        if ($key == 'phone2' AND $updated['phone2'] == $updated['phone1'])
          continue;

        if ($key == 'backup_pharmacy') {

         echo "
         SirumWeb_AddExternalPharmacy '$updated[pharmacy_npi]', '$updated[pharmacy_name], $updated[pharmacy_phone], $updated[pharmacy_address]', '$updated[pharmacy_address]', '$updated[pharmacy_city]', '$updated[pharmacy_state]', '$updated[pharmacy_zip]', '$updated[pharmacy_phone]', '$updated[pharmacy_fax]'";

         echo "
         SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '1', '$updated[pharmacy_name]'";

         $user_def2 = substr("$updated[pharmacy_npi],$updated[pharmacy_fax],$updated[pharmacy_phone],$updated[pharmacy_address]", 0, 50-10-10-10-3);

         echo "
         SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '2', '$user_def2'";

         echo "
         SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '3', '$updated[payment_method_default]'";

         $user_def4 = "$updated[payment_card_last4],$updated[payment_card_date_expired],$updated[payment_card_type],'".($updated['payment_coupon'] ?: $updated['tracking_coupon']);

         echo "
         SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '4', '$user_def4'");
        }

        if ($key == 'email') {
          echo "
          SirumWeb_AddUpdatePatEmail '$updated[patient_id_cp]', '$updated[email]'";
        }

        if ($key == 'medications_other') {
          echo "
          SirumWeb_AddToPatientComment '$updated[patient_id_cp]', '$updated[medications_other]'";
        }

        if (in_array($key, ['first_name', 'last_name', 'birth_date','language', 'patient_autofill'] ) {
          echo "
          SirumWeb_AddUpdatePatient '$updated[first_name]', '$updated[last_name]', '$updated[birth_date]', '$updated[phone1]', '$updated[language]', $updated[patient_autofill]";
        }

        if (in_array($key, ['patient_address1', 'patient_address2', 'patient_city', 'patient_zip'] ) {
          echo "
          SirumWeb_AddUpdatePatHomeAddr '$updated[patient_id_cp]', '$updated[patient_address1]', $updated[patient_address2], NULL, '$updated[patient_city]', 'GA', '$updated[patient_zip]', 'US'";
        }

        if ($key == 'phone1') {
          echo "
          SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone1]'";
        }

        if () {
          $allergies = json_encode([
            'allergies_none' => !!$updated['allergies_none'],
            'allergies_aspirin' => !!$updated['allergies_aspirin'],
            'allergies_amoxicillin' => !!$updated['allergies_amoxicillin'],
            'allergies_ampicillin' => !!$updated['allergies_ampicillin'],
            'allergies_azithromycin' => !!$updated['allergies_azithromycin'],
            'allergies_cephalosporins' => !!$updated['allergies_cephalosporins'],
            'allergies_codeine' => !!$updated['allergies_codeine'],
            'allergies_erythromycin' => !!$updated['allergies_erythromycin'],
            'allergies_penicillin' => !!$updated['allergies_penicillin'],
            'allergies_salicylates' => !!$updated['allergies_salicylates'],
            'allergies_sulfa' => !!$updated['allergies_sulfa'],
            'allergies_tetracycline' => !!$updated['allergies_tetracycline'],
            'allergies_other' => !!$updated['allergies_other']
          ]);

          echo "
          SirumWeb_AddRemove_Allergies '$updated[patient_id_cp]', '$allergies'";
        }

        //$set_patients[$key] = "$key = '$new_val'";
      }

      if ( ! $new_val AND $old_val) {

        $wc_key = isset($cp_to_wc[$key]) ? $cp_to_wc[$key] : $key;
        $wc_val = $old_val;

        if ($wc_key == 'medications_other') continue;

        if (substr($wc_key, 8) == 'billing_') {
          $sql = "UPDATE wp_usermeta SET meta_value = $old_val WHERE user_id = $updated[patient_id_wc] AND meta_key = '$wc_key'";
          echo "
          $sql";
          $mysql->run($sql);
          continue;
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
      $mysql->run($sql);
    }

    if ($set_usermeta) {
      $sql = "INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES $set_usermeta";
      echo "
      $sql";
      $mysql->run($sql);
    }

    if ($set_patients) {



    }

    /*
    function update_pharmacy($guardian_id, $pharmacy) {

      if ( ! $guardian_id) return;

      $store = json_decode(stripslashes($pharmacy));

      $store_name = str_replace("'", "''", $store->name); //We need to escape single quotes in case pharmacy name has a ' for example Lamar's Pharmacy
      $store_street = str_replace("'", "''", $store->street);

      db_run("SirumWeb_AddExternalPharmacy '$store->npi', '$store_name, $store->phone, $store_street', '$store_street', '$store->city', '$store->state', '$store->zip', '$phone', '$fax'");

      db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '1', '$store_name'");

      //Because of Guardian's 50 character limit for UD fields and 3x 10 character fields with 3 delimiters, we need to cutoff street
      $user_def_2 = $store->npi.','.cleanPhone($store->fax).','.cleanPhone($store->phone).','.substr($store_street, 0, 50-10-10-10-3);
      return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '2', '$user_def_2'");
    }

    function update_payment_method($guardian_id, $value) {
      if ( ! $guardian_id) return;
      return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '3', '$value'");
    }

    function update_card_and_coupon($guardian_id, $card = [], $coupon = "") {
      if ( ! $guardian_id) return;
      //Meet guardian 50 character limit
      //Last4 4, Month 2, Year 2, Type (Mastercard = 10), Delimiter 4, So coupon will be truncated if over 28 characters
      $value = $card['last4'].','.$card['month'].'/'.substr($card['year'] ?: '', 2).','.$card['type'].','.$coupon;

      return db_run("SirumWeb_AddUpdatePatientUD '$guardian_id', '4', '$value'");
    }
    */
  }
}
