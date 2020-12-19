<?php

require_once 'exports/export_wc_patients.php';
require_once 'exports/export_cp_patients.php';

use Sirum\Logging\SirumLog;

function update_patients_wc($changes) {

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $msg = "update_patient_wc: all changes. $count_deleted deleted, $count_created created, $count_updated updated.";
  echo "\n$msg\n";
  log_info($msg, [
    'deleted_count' => $count_deleted,
    'created_count' => $count_created,
    'updated_count' => $count_updated
  ]);

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  function name_mismatch($new, $old) {
    $new = str_replace(['-'], [' '], $new);
    $old = str_replace(['-'], [' '], $old);
    return stripos($new, $old) === false AND stripos($old, $new) === false;
  }

  $created_mismatched = 0;
  $created_matched = 0;
  $created_needs_form = 0;
  $created_new_to_cp = 0;

  foreach($changes['created'] as $created) {
    SirumLog::$subroutine_id = "patients-wc-created-".sha1(serialize($created));

    //Overrite Rx Messages everytime a new order created otherwis same message would stay for the life of the Rx

    SirumLog::debug(
      "update_patients_wc: WooCommerce PATIENT Created",
      [
          'created' => $created,
          'source'  => 'WooCommerce',
          'type'    => 'patients',
          'event'   => 'created'
      ]
    );

    $patient = find_patient_wc($mysql, $created);

    if ( ! empty($patient[0]['patient_id_cp'])) {
      $created_matched++;
      match_patient_wc($mysql, $created, $patient[0]['patient_id_cp']);
    }
    else if ( ! $created['pharmacy_name']) {
      $created_needs_form++;
      //Registration Started but Not Complete (first 1/2 of the registration form)
    }
    else if ($created['patient_state'] != 'GA') {
      //log_error("update_patients_wc: patient in WC but not in CP, because patient is not in GA $created[first_name] $created[last_name] wc:$created[patient_id_wc]", [$patient, $created]);
    }

    else {

      $created_new_to_cp++;
      $created_date = "Created:".date('Y-m-d H:i:s');

      $salesforce = [
        "subject"   => "Fix Duplicate Patient",
        "body"      => "Patient $created[first_name] $created[last_name] $created[birth_date] (WC user_id:$created[patient_id_wc]) in WC but not in CP. Fix and notify patient if necessary. Likely #1 a duplicate user in WC (forgot login so reregistered with slightly different name or DOB), #2 patient inactivated in CP (remove their birthday in WC to deactivate there), or #3 inconsistent birth_date between Rx in CP and Registration in WC. $created_date",
        "contact"   => "$created[first_name] $created[last_name] $created[birth_date]",
        "assign_to" => ".Update Name/DOB - Admin",
        "due_date"  => date('Y-m-d')
      ];

      $event_title = "$salesforce[subject]: $salesforce[contact] $created_date";

      //In WC Patient Changes we don't have the "patient_date_added" or "patient_date_changed" CP fields,
      //"patient_date_updated" will always be within past 10mins, so use "patient_date_registered"
      $secs = time() - strtotime($created['patient_date_registered']);

      if ($secs/60 < 30) { //Otherwise gets repeated every 10mins.
        create_event($event_title, [$salesforce]);
        log_error("New $event_title", [$secs, $patient, $created]);
      }
      else if (date('h') == '11') { //Twice a day so use a lower case h for 12 hour clock instead of 24 hour.
        log_error("Old $event_title", [$secs, $patient, $created]);
      }
    }
    SirumLog::resetSubroutineId();
  }

  log_notice('created counts', [
    '$created_mismatched' => $created_mismatched,
    '$created_matched' => $created_matched,
    '$created_needs_form' => $created_needs_form,
    '$created_new_to_cp' => $created_new_to_cp
  ]);

  foreach($changes['deleted'] as $i => $deleted) {

    SirumLog::$subroutine_id = "patients-wc-deleted-".sha1(serialize($deleted));

    SirumLog::debug(
      "update_patients_wc: WooCommerce PATIENT deleted",
      [
        'deleted' => $deleted,
        'source'  => 'WooCommerce',
        'type'    => 'patients',
        'event'   => 'deleted'
      ]
    );

    //Dummy accounts that have been cleared out of WC
    if (stripos($deleted['first_name'], 'Test') !== false OR stripos($deleted['first_name'], 'User') !== false OR stripos($deleted['email'], 'user') !== false OR stripos($deleted['email'], 'test') !== false) {
      echo "UPDATE cppat SET pat_status_cn = 2 WHERE pat_id = $deleted[patient_id_cp]";
      //$mssql->run($sql);
      continue;
    }

    print_r([
      "what's going on here?",
      'patient_id_wc' => $deleted['patient_id_wc'],
      'deleted' => $deleted
    ]);

    if ($deleted['patient_id_wc'])
      log_error('update_patients_wc deleted: patient was just deleted from WC', $deleted);
    //else
    //  log_error('update_patients_wc: never added', $deleted);
    SirumLog::resetSubroutineId();
  }

  foreach($changes['updated'] as $i => $updated) {

      SirumLog::$subroutine_id = "patients-wc-updated-".sha1(serialize($updated));

      SirumLog::debug(
        "update_patients_wc: WooCommerce PATIENT updated",
        [
            'updated' => $updated,
            'source'  => 'WooCommerce',
            'type'    => 'patients',
            'event'   => 'updated'
        ]
      );

    if ( ! $updated['patient_id_cp']) {
      $patient = find_patient_wc($mysql, $updated);
      match_patient_wc($mysql, $updated, $patient[0]['patient_id_cp']);
      continue;
    }

    $changed = changed_fields($updated);

    $changed
      ? log_notice("update_patients_wc: updated changed $updated[first_name] $updated[last_name] $updated[birth_date] cp:$updated[patient_id_cp] wc:$updated[patient_id_wc]", $changed)
      : log_error("update_patients_wc: updated no change? $updated[first_name] $updated[last_name] $updated[birth_date] cp:$updated[patient_id_cp] wc:$updated[patient_id_wc]", $updated);

    if ( ! $updated['email'] AND $updated['old_email']) {
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'email', $updated['old_email']);
    } else if ($updated['email'] !== $updated['old_email']) {
      upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatEmail '$updated[patient_id_cp]', '$updated[email]'");
    }

    if (
        ( ! $updated['patient_address1'] AND $updated['old_patient_address1']) OR
        ( ! $updated['patient_address2'] AND $updated['old_patient_address2']) OR
        ( ! $updated['patient_city'] AND $updated['old_patient_city']) OR
        (strlen($updated['patient_state']) != 2 AND strlen($updated['old_patient_state']) == 2) OR
        (strlen($updated['patient_zip']) != 5 AND strlen($updated['old_patient_zip']) == 5)
    ) {

        log_notice("update_patients_wc: adding address. $updated[first_name] $updated[last_name] $updated[birth_date]", ['changed' => $changed, 'updated' => $updated]);

        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_address1', $updated['old_patient_address1']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_address2', $updated['old_patient_address2']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_city', $updated['old_patient_city']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_state', $updated['old_patient_state']);
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_zip', $updated['old_patient_zip']);

    } else if (
        $updated['patient_address1'] !== $updated['old_patient_address1'] OR
        $updated['patient_address2'] !== $updated['old_patient_address2'] OR
        $updated['patient_city'] !== $updated['old_patient_city'] OR
        (strlen($updated['patient_state']) == 2 AND $updated['patient_state'] !== $updated['old_patient_state']) OR
        (strlen($updated['patient_zip']) == 5 AND $updated['patient_zip'] !== $updated['old_patient_zip'])
    ) {

      $address1 = escape_db_values($updated['patient_address1']);
      $address2 = escape_db_values($updated['patient_address2']);
      $city = escape_db_values($updated['patient_city']);

      $address3 = 'NULL';
      if ($updated['patient_state'] != 'GA') {
        log_error("update_patients_wc: updated address-mismatch. $updated[first_name] $updated[last_name] $updated[birth_date]");
        $address3 = "'!!!! WARNING NON-GEORGIA ADDRESS !!!!'";
      }

      $sql = "EXEC SirumWeb_AddUpdatePatHomeAddr '$updated[patient_id_cp]', '$address1', '$address2', $address3, '$city', '$updated[patient_state]', '$updated[patient_zip]', 'US'";

      log_notice("update_patients_wc: updated address-mismatch. $updated[first_name] $updated[last_name] $updated[birth_date]", ['sql' => $sql, 'changed' => $changed, 'updated' => $updated]);
      upsert_patient_cp($mssql, $sql);
    }

    if ($updated['patient_date_registered'] != $updated['old_patient_date_registered']) {

      $sql = "
        UPDATE gp_patients SET patient_date_registered = '$updated[patient_date_registered]' WHERE patient_id_wc = $updated[patient_id_wc]
      ";

      $mysql->run($sql);

      log_notice("update_patients_wc: patient_registered. $updated[first_name] $updated[last_name] $updated[birth_date]", ['sql' => $sql]);

    }

    //NOTE: Different/Reverse logic here. Deleting in CP should save back into WC
    if (
        ($updated['payment_coupon'] AND ! $updated['old_payment_coupon']) OR
        ($updated['tracking_coupon'] AND ! $updated['old_tracking_coupon'])
    ) {
      $user_def4 = "$updated[payment_card_last4],$updated[payment_card_date_expired],$updated[payment_card_type],".($updated['payment_coupon'] ?: $updated['tracking_coupon']);
      echo "
      ".json_encode($updated, JSON_PRETTY_PRINT);
      upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '4', '$user_def4'");
    } else if (
            //$updated['payment_card_last4'] !== $updated['old_payment_card_last4'] OR
            //$updated['payment_card_date_expired'] !== $updated['old_payment_card_date_expired'] OR
            //$updated['payment_card_type'] !== $updated['old_payment_card_type'] OR
            $updated['payment_coupon'] !== $updated['old_payment_coupon'] OR //Still allow for deleteing coupons in CP
            $updated['tracking_coupon'] !== $updated['old_tracking_coupon'] //Still allow for deleteing coupons in CP
    ) {
      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'coupon', $updated['old_payment_coupon'] ?: $updated['old_tracking_coupon']);
    }

    if ( ! $updated['phone1'] AND $updated['old_phone1']) {
      //Phone was deleted in WC, so delete in CP
      delete_cp_phone($mssql, $updated['patient_id_cp'], 6);
    } else if (strlen($updated['phone1']) < 10 AND strlen($updated['old_phone1']) >= 10) {
      //Phone added to WC was malformed, so revert to old phone
      update_wc_phone1($mysql, $updated['patient_id_wc'], $updated['old_phone1']);
    } else if ($updated['phone1'] !== $updated['old_phone1']) {
      //Well-formed added to WC so now add to CP
      upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone1]'");
    }

    if ( ! $updated['phone2'] AND $updated['old_phone2']) {
      //Phone was deleted in WC, so delete in CP
      delete_cp_phone($mssql, $updated['patient_id_cp'], 9);
    } else if ($updated['phone2'] AND $updated['phone2'] == $updated['phone1']) {
      //Phone added to WC was a duplicate
      update_wc_phone2($mysql, $updated['patient_id_wc'], NULL);

    } else if (strlen($updated['phone2']) < 10 AND strlen($updated['old_phone2']) >= 10) {
      //Phone added to WC was malformed, so revert to old phone
      update_wc_phone2($mysql, $updated['patient_id_wc'], $updated['old_phone2']);
    } else if ($updated['phone2'] !== $updated['old_phone2']) {
      //Well-formed, non-duplicated phone added to WC so now add to CP
      log_error("EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone2]', 9", $updated);
      upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone2]', 9");
    }

    //If pharmacy name changes then trust WC over CP
    if ($updated['pharmacy_name'] AND $updated['pharmacy_name'] !== $updated['old_pharmacy_name']) {

      $user_def1 = escape_db_values($updated['pharmacy_name']);
      $user_def2 = substr("$updated[pharmacy_npi],$updated[pharmacy_fax],$updated[pharmacy_phone],$updated[pharmacy_address]", 0, 50);

      upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '1', '$user_def1'");
      upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '2', '$user_def2'");
    } else if ( //If pharmacy name is the same trust CP data over WC data so always update WC
        $updated['pharmacy_npi'] !== $updated['old_pharmacy_npi'] OR
        $updated['pharmacy_fax'] !== $updated['old_pharmacy_fax'] OR
        $updated['pharmacy_phone'] !== $updated['old_pharmacy_phone'] //OR
        //$updated['pharmacy_address'] !== $updated['old_pharmacy_address'] // We only save a partial address in CP so will always differ
    ) {

      upsert_patient_wc($mysql, $updated['patient_id_wc'], 'backup_pharmacy', json_encode([
        'name' => escape_db_values($updated['old_pharmacy_name']),
        'npi' => $updated['old_pharmacy_npi'],
        'fax' => $updated['old_pharmacy_fax'],
        'phone' => $updated['old_pharmacy_phone'],
        'street' => $updated['pharmacy_address'] // old_pharamcy address is not populated since We only save a partial address in CP so will always differ
      ]));
    }

    if ($updated['payment_method_default'] AND ! $updated['old_payment_method_default']) {

      upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '3', '$updated[payment_method_default]'");

    } else if ($updated['payment_method_default'] !== $updated['old_payment_method_default']) {

      log_error('update_patients_wc: updated payment_method_default. Deleting Autopay Reminders', $updated);

      if ($updated['old_payment_method_default'] == PAYMENT_METHOD['MAIL'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['MAIL']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['AUTOPAY'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['AUTOPAY']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['ONLINE'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['ONLINE']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['COUPON'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['COUPON']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['CARD EXPIRED'])
        upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['CARD EXPIRED']);

      else
        log_error("NOT SURE WHAT TO DO FOR PAYMENT METHOD $updated");

    }

    if ( ! $updated['first_name'] OR ! $updated['first_name'] OR ! $updated['birth_date']) {

       log_error("Patient Set Incorrectly", [$changed, $updated]);

    } else if (
        name_mismatch($updated['first_name'],  $updated['old_first_name']) OR
        name_mismatch($updated['last_name'],  $updated['old_last_name'])
    ) {

      $error = [
        "updated" => $updated,
        "changed" => $changed
      ];

      if (
        stripos($updated['first_name'], 'TEST') === false
        and stripos($updated['last_name'], 'TEST') === false) {
        SirumLog::alert('Patient Name Misspelled or Identity Changed?', $error);
      } else {
        log_error("Patient Name Misspelled or Identity Changed?", $error);
      }


      //upsert_patient_wc($mysql, $updated['patient_id_wc'], 'first_name', $updated['old_first_name']);
      //upsert_patient_wc($mysql, $updated['patient_id_wc'], 'last_name', $updated['old_last_name']);
      //upsert_patient_wc($mysql, $updated['patient_id_wc'], 'birth_date', $updated['old_birth_date']);
      //upsert_patient_wc($mysql, $updated['patient_id_wc'], 'language', $updated['old_language']);

    } else if (
      $updated['first_name'] !== $updated['old_first_name'] OR
      $updated['last_name'] !== $updated['old_last_name'] OR
      $updated['birth_date'] !== $updated['old_birth_date'] OR
      $updated['language'] !== $updated['old_language']
    ) {
      $sp = "EXEC SirumWeb_AddUpdatePatient '$updated[first_name]', '$updated[last_name]', '$updated[birth_date]', '$updated[phone1]', '$updated[language]'";
      log_notice("Patient Name/Identity Updated.  If called repeatedly there is likely a two matching CP users", [$sp, $changed]);
      upsert_patient_cp($mssql, $sp);
    }

    if (
      $updated['allergies_none'] !== $updated['old_allergies_none'] OR
      $updated['allergies_aspirin'] !== $updated['old_allergies_aspirin'] OR
      $updated['allergies_amoxicillin'] !== $updated['old_allergies_amoxicillin'] OR
      $updated['allergies_azithromycin'] !== $updated['old_allergies_azithromycin'] OR
      $updated['allergies_cephalosporins'] !== $updated['old_allergies_cephalosporins'] OR
      $updated['allergies_codeine'] !== $updated['old_allergies_codeine'] OR
      $updated['allergies_erythromycin'] !== $updated['old_allergies_erythromycin'] OR
      $updated['allergies_nsaids'] !== $updated['old_allergies_nsaids'] OR
      $updated['allergies_penicillin'] !== $updated['old_allergies_penicillin'] OR
      $updated['allergies_salicylates'] !== $updated['old_allergies_salicylates'] OR
      $updated['allergies_sulfa'] !== $updated['old_allergies_sulfa'] OR
      $updated['allergies_tetracycline'] !== $updated['old_allergies_tetracycline'] OR
      $updated['allergies_other'] !== $updated['old_allergies_other']
    ) {

      $allergy_array = [
        'allergies_none' => $updated['allergies_none'] ?: '',
        'allergies_aspirin' => $updated['allergies_aspirin'] ?: '',
        'allergies_amoxicillin' => $updated['allergies_amoxicillin'] ?: '',
        'allergies_azithromycin' => $updated['allergies_azithromycin'] ?: '',
        'allergies_cephalosporins' => $updated['allergies_cephalosporins'] ?: '',
        'allergies_codeine' => $updated['allergies_codeine'] ?: '',
        'allergies_erythromycin' => $updated['allergies_erythromycin'] ?: '',
        'allergies_nsaids' => $updated['allergies_nsaids'] ?: '',
        'allergies_penicillin' => $updated['allergies_penicillin'] ?: '',
        'allergies_salicylates' => $updated['allergies_salicylates'] ?: '',
        'allergies_sulfa' => $updated['allergies_sulfa'] ?: '',
        'allergies_tetracycline' => $updated['allergies_tetracycline'] ?: '',
        'allergies_other' => escape_db_values($updated['allergies_other'])
      ];

      $allergies = json_encode(utf8ize($allergy_array), JSON_UNESCAPED_UNICODE);

      if ($allergies)
        $res = upsert_patient_cp($mssql, "EXEC SirumWeb_AddRemove_Allergies '$updated[patient_id_cp]', '$allergies'");
      else
        log_error("update_patients_wc: EXEC SirumWeb_AddRemove_Allergies '$updated[patient_id_cp]', '$allergies'", [$res, json_last_error_msg(), $allergy_array]);
    }

    if ($updated['medications_other'] !== $updated['old_medications_other']) {
      $patient = find_patient_wc($mysql, $updated);

      if (@$patient['patient_note'])
        echo "
        Patient Note: $patient[patient_note]";

      export_cp_patient_save_medications_other($mssql, $updated);
    }
  }

  SirumLog::resetSubroutineId();
}
