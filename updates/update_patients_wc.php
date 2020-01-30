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

    $cp_to_wc = [
      'email' => 'user_email',
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
    $SirumWeb_AddExternalPharmacy = true;
    $SirumWeb_AddUpdatePatientUD1 = true;
    $SirumWeb_AddUpdatePatientUD2 = true;
    $SirumWeb_AddUpdatePatientUD3 = true;
    $SirumWeb_AddUpdatePatientUD4 = true;
    $SirumWeb_AddUpdatePatEmail = true;
    $SirumWeb_AddToPatientComment = true;
    $SirumWeb_AddUpdatePatientLang = true;
    $SirumWeb_AddUpdatePatient = true;
    $SirumWeb_AddUpdatePatHomeAddr = true;
    $SirumWeb_AddUpdatePatHomePhone = true;
    $SirumWeb_AddUpdatePatCellPhone = true;
    $SirumWeb_AddRemove_Allergies = true;
    foreach ($changed as $key => $val) {

      $old_val = $updated['old_'.$key];
      $new_val = $updated[$key];

      if ( ! $new_val AND $old_val) {

        $wc_key = isset($cp_to_wc[$key]) ? $cp_to_wc[$key] : $key;
        $wc_val = @mysql_escape_string($old_val);

        if ($wc_key == 'medications_other') continue;

        if ($wc_key == 'user_email') {
          $sql = "UPDATE wp_users SET user_email = '$wc_val' WHERE ID = $updated[patient_id_wc]";
          echo "
          $sql";
          $mysql->run($sql);
          continue;
        }

        if (substr($wc_key, 8) == 'billing_') {
          $sql = "UPDATE wp_usermeta SET meta_value = '$wc_val' WHERE user_id = $updated[patient_id_wc] AND meta_key = '$wc_key'";
          echo "
          $sql";
          $mysql->run($sql);
          continue;
        }

        if ($wc_key == 'backup_pharmacy')
          $wc_val = json_encode([
            'name' => str_replace("'", "''", $updated['old_pharmacy_name']),
            'npi' => $updated['old_pharmacy_npi'],
            'fax' => $updated['old_pharmacy_fax'],
            'phone' => $updated['old_pharmacy_phone'],
            'street' => $updated['old_pharmacy_address']
          ]);


        $set_usermeta[$wc_key] = "(NULL, $updated[patient_id_wc], '$wc_key',  '$wc_val')";
      }

      else if ($new_val !== $old_val) {

        if ($SirumWeb_AddExternalPharmacy && in_array($key, ['pharmacy_npi','pharmacy_name','pharmacy_phone','pharmacy_fax','pharmacy_zip','pharmacy_address','pharmacy_city'])) {
         $SirumWeb_AddExternalPharmacy = false;
         //echo "
         //$updated[first_name] $updated[last_name] $updated[birth_date] $changed[$key] SirumWeb_AddExternalPharmacy '$updated[pharmacy_npi]', '$updated[pharmacy_name], $updated[pharmacy_phone], $updated[pharmacy_address]', '$updated[pharmacy_address]', '$updated[pharmacy_city]', '$updated[pharmacy_state]', '$updated[pharmacy_zip]', '$updated[pharmacy_phone]', '$updated[pharmacy_fax]'";
        }

        if ($SirumWeb_AddUpdatePatientUD1 && $key == 'pharmacy_name') {
         $SirumWeb_AddUpdatePatientUD1 = false;
         $pharmacy_name = str_replace("'", "''", $updated['pharmacy_name']);
         $mssql->run("EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '1', '$pharmacy_name'");
         echo "
         RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '1', '$updated[pharmacy_name]'";
        }

        if ($key == 'pharmacy_fax')
          echo "
          $key $SirumWeb_AddUpdatePatientUD2 new:$updated[pharmacy_fax] old:$updated[old_pharmacy_fax] ".strlen($updated['pharmacy_fax']);

        if ($SirumWeb_AddUpdatePatientUD2 && (strlen($updated['pharmacy_fax']) >= 10) && in_array($key, ['pharmacy_npi','pharmacy_fax','pharmacy_phone'])) {
         $user_def2 = substr("$updated[pharmacy_npi],$updated[pharmacy_fax],$updated[pharmacy_phone],$updated[pharmacy_address]", 0, 50);

         $SirumWeb_AddUpdatePatientUD2 = false;

         $mssql->run("EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '2', '$user_def2'");
         echo "
         RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '2', '$user_def2'";
        }

        if ($SirumWeb_AddUpdatePatientUD3 && $key == 'payment_method_default') {
        $SirumWeb_AddUpdatePatientUD3 = false;

         $mssql->run("EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '3', '$updated[payment_method_default]'");
         echo "
         RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '3', '$updated[payment_method_default]'";
        }

        if ($SirumWeb_AddUpdatePatientUD4 && in_array($key, ['payment_card_last4','payment_card_date_expired','payment_card_type','payment_coupon','tracking_coupon'])) {
         $user_def4 = "$updated[payment_card_last4],$updated[payment_card_date_expired],$updated[payment_card_type],".($updated['payment_coupon'] ?: $updated['tracking_coupon']);

         $SirumWeb_AddUpdatePatientUD4 = false;

         $mssql->run("EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '4', '$user_def4'");
         echo "
         RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '4', '$user_def4'";
        }

        if ($SirumWeb_AddUpdatePatEmail && $key == 'email') {
          $SirumWeb_AddUpdatePatEmail = false;

          $wc_val = $updated['email'] ? "'$updated[email]'" : 'NULL';


          $mssql->run("EXEC SirumWeb_AddUpdatePatEmail '$updated[patient_id_cp]', '$updated[email]'");
          echo "
          RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] SirumWeb_AddUpdatePatEmail '$updated[patient_id_cp]', '$updated[email]'";
        }

        if ($SirumWeb_AddToPatientComment && $key == 'medications_other') {
          $SirumWeb_AddToPatientComment = false;
          echo "
          $updated[first_name] $updated[last_name] $updated[birth_date] $changed[$key] SirumWeb_AddToPatientComment '$updated[patient_id_cp]', '$updated[medications_other]'";
        }

        if ($SirumWeb_AddUpdatePatientLang && in_array($key, ['language'])) {
          $SirumWeb_AddUpdatePatientLang = false;
          $mssql->run("EXEC SirumWeb_AddUpdatePatient '$updated[first_name]', '$updated[last_name]', '$updated[birth_date]', '$updated[phone1]', '$updated[language]'");
          echo "
          RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] SirumWeb_AddUpdatePatient '$updated[first_name]', '$updated[last_name]', '$updated[birth_date]', '$updated[phone1]', '$updated[language]'";
        }

        if ($SirumWeb_AddUpdatePatient && in_array($key, ['first_name', 'last_name', 'birth_date'])) {
          $SirumWeb_AddUpdatePatient = false;
          echo "
          $updated[first_name] $updated[last_name] $updated[birth_date] $changed[$key] SirumWeb_AddUpdatePatient '$updated[first_name]', '$updated[last_name]', '$updated[birth_date]', '$updated[phone1]', '$updated[language]'";
        }

        if ($SirumWeb_AddUpdatePatHomeAddr && in_array($key, ['patient_address1', 'patient_address2', 'patient_city', 'patient_zip'])) {
          //$SirumWeb_AddUpdatePatHomeAddr = false;

          $wc_key = isset($cp_to_wc[$key]) ? $cp_to_wc[$key] : $key;
          $wc_val = @mysql_escape_string($old_val);

          $wc_val = $wc_val ? "'$wc_val'" : 'NULL';

          $sql1 = "SELECT * FROM wp_usermeta WHERE user_id = $updated[patient_id_wc] AND meta_key = '$wc_key'";

          $exists = $mysql->run($sql1);

          if (isset($exists[0][0])) {
            $sql2 = "UPDATE wp_usermeta SET meta_value = $wc_val WHERE user_id = $updated[patient_id_wc] AND meta_key = '$wc_key'";
          } else {
            $sql2 = "INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES (NULL, $updated[patient_id_wc], '$wc_key', $wc_val)";
          }

          echo "
          RAN $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] $sql2";
          $mysql->run($sql2);
        }

        if ($SirumWeb_AddUpdatePatHomePhone && $key == 'phone1') {
          $SirumWeb_AddUpdatePatHomePhone = false;

          if (strlen($updated['phone1']) >= 10) {
            $sql = "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone1]'";
            $mssql->run($sql);
            echo "
            RAN $updated[first_name] $updated[last_name] $updated[birth_date] $sql";
          }
          else if (strlen($updated['old_phone1']) >= 10) {
            $sql = "UPDATE wp_usermeta SET meta_value = '$updated[old_phone1]' WHERE user_id = $updated[patient_id_wc] AND meta_key = 'phone'";
            echo "
            UPDATING phone1 within WC $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] $sql";
            $mysql->run($sql);
          }
        }

        if ($SirumWeb_AddUpdatePatCellPhone && $key == 'phone2' && (strlen($updated['phone2']) >= 10)) {
          $SirumWeb_AddUpdatePatCellPhone = false;

          if ($updated['phone1'] == $updated['phone2'] AND $updated['old_phone2']) {
            $sql = "UPDATE wp_usermeta SET meta_value = '$updated[old_phone2]' WHERE user_id = $updated[patient_id_wc] AND meta_key = 'billing_phone'";
            echo "
            UPDATING phone2 within WC $updated[first_name] $updated[last_name] $updated[birth_date] $updated[phone1] $key $changed[$key] $sql";
            $mysql->run($sql);
          }
          else if ($updated['phone1'] == $updated['phone2'] AND ! $updated['old_phone2']) {
            $sql = "UPDATE wp_usermeta SET meta_value = NULL WHERE user_id = $updated[patient_id_wc] AND meta_key = 'billing_phone'";
            echo "
            DELETING phone2 from WC $updated[first_name] $updated[last_name] $updated[birth_date] $updated[phone1] $key $changed[$key] $sql";
            $mysql->run($sql);
          } else {
            $sql = "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone2]', 9";
            $mssql->run($sql);
            echo "
            ADDING phone2 to CP $updated[first_name] $updated[last_name] $updated[birth_date] $key $changed[$key] $sql";
          }
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

        //$set_patients[$key] = "$key = '$new_val'";
      }
    }

    //$set_patients = implode(', ', $set_patients);
    $set_usermeta = implode(', ', $set_usermeta);

    //if ($set_patients)
    //  log_error("update_patients_wc: UPDATE cppat SET $set_patients WHERE pat_id = $updated[patient_id_cp]");

    if ( ! empty($changed['last_name'])) {
      $wc_val = @mysql_escape_string($updated['old_last_name']);
      $sql = "UPDATE wp_usermeta SET meta_value = '$wc_val' WHERE user_id = $updated[patient_id_wc] AND meta_key = 'last_name'";
      echo "
      RAN $updated[first_name] $updated[last_name] $updated[birth_date] $changed[last_name] $sql";
      $mysql->run($sql);
    }

    if ( ! empty($changed['first_name'])) {
      $wc_val = @mysql_escape_string($updated['old_first_name']);
      $sql = "UPDATE wp_usermeta SET meta_value = '$wc_val' WHERE user_id = $updated[patient_id_wc] AND meta_key = 'first_name'";
      echo "
      RAN $updated[first_name] $updated[last_name] $updated[birth_date] $changed[first_name] $sql";
      $mysql->run($sql);
    }

    if ($set_usermeta) {
      $sql = "INSERT wp_usermeta (umeta_id, user_id, meta_key, meta_value) VALUES $set_usermeta";
      echo "
      RAN $updated[first_name] $updated[last_name] $updated[birth_date] $sql";
      $mysql->run($sql);
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
