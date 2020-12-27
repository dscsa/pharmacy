<?php

require_once 'exports/export_wc_patients.php';
require_once 'exports/export_cp_patients.php';
require_once 'helpers/helper_matching.php';

use Sirum\Logging\SirumLog;

function update_patients_wc($changes) {

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
  echo $msg;
  log_info("update_patients_wc: all changes. $msg", [
    'deleted_count' => $count_deleted,
    'created_count' => $count_created,
    'updated_count' => $count_updated
  ]);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  $loop_timer = microtime(true);

  foreach($changes['created'] as $created) {
    SirumLog::$subroutine_id = "patients-wc-created-".sha1(serialize($created));

    //Overrite Rx Messages everytime a new order created otherwis same message would stay for the life of the Rx

    SirumLog::debug(
      "update_patients_wc: WooCommerce PATIENT Created $created[first_name] $created[last_name] $created[birth_date]",
      [
          'created' => $created,
          'source'  => 'WooCommerce',
          'type'    => 'patients',
          'event'   => 'created'
      ]
    );

    if ( ! $created['pharmacy_name']) {

      $limit = 24; //Delete Incomplete Registrations after 24 hours
      $hours = round((time() - strtotime($created['patient_date_registered']))/60/60, 1);

      if ($hours > $limit) {
        SirumLog::debug(
          "update_patients_wc: deleting incomplete registration for $created[first_name] $created[last_name] $created[birth_date] after $limit hours ",
          [
              'created' => $created,
              'limit'   => $limit,
              'hours'   => $hours,
              'source'  => 'WooCommerce',
              'type'    => 'patients',
              'event'   => 'created'
          ]
        );

        //Note we only do this because the registration was incomplete
        //if completed we should move them to inactive or deceased
        wc_delete_patient($mysql, $created['patient_id_wc']);

        $date = "Created:".date('Y-m-d H:i:s');

        $salesforce = [
          "subject"   => "$created[first_name] $created[last_name] $created[birth_date] started registration but did not finish in time",
          "body"      => "Patient's initial registration was deleted because it was not finised within $limit hours.  Please call them to register! $date",
          "contact"   => "$created[first_name] $created[last_name] $created[birth_date]",
          "assign_to" => ".Register New Patient - Tech",
          "due_date"  => date('Y-m-d')
        ];

        create_event($salesforce['subject'], [$salesforce]);

      } else {
        echo "\nincomplete registration for $created[first_name] $created[last_name] $created[birth_date] was started on $created[patient_date_registered] and is $hours hours old ";
      }

      //Registration Started but Not Complete (first 1/2 of the registration form)
      continue;
    }

    $is_match = is_patient_match($mysql, $created);

    if ($is_match) {
      match_patient($mysql, $is_match['patient_id_cp'], $is_match['patient_id_wc']);
    }
  }
  log_timer('patients-wc-created', $loop_timer, $count_created);


  $loop_timer = microtime(true);

  foreach($changes['deleted'] as $i => $deleted) {

    SirumLog::$subroutine_id = "patients-wc-deleted-".sha1(serialize($deleted));

    $alert = [
      'deleted' => $deleted,
      'source'  => 'WooCommerce',
      'type'    => 'patients',
      'event'   => 'deleted'
    ];

    SirumLog::alert("update_patients_wc: WooCommerce PATIENT deleted $deleted[first_name] $deleted[last_name] $deleted[birth_date]", $alert);

    print_r($alert);
  }

  log_timer('patients-wc-deleted', $loop_timer, $count_deleted);


  $loop_timer = microtime(true);

  foreach($changes['updated'] as $i => $updated) {

    SirumLog::$subroutine_id = "patients-wc-updated-".sha1(serialize($updated));

    $changed = changed_fields($updated);

    SirumLog::debug(
      "update_patients_wc: WooCommerce PATIENT updated",
      [
          'updated' => $updated,
          'changed' => $changed,
          'source'  => 'WooCommerce',
          'type'    => 'patients',
          'event'   => 'updated'
      ]
    );

    echo "\nwc patient updated! $updated[first_name] $updated[last_name] $updated[birth_date] cp:$updated[patient_id_cp] wc:$updated[patient_id_wc] ".print_r($changed, true);

    $changed
      ? log_notice("update_patients_wc: updated changed $updated[first_name] $updated[last_name] $updated[birth_date] cp:$updated[patient_id_cp] wc:$updated[patient_id_wc]", $changed)
      : log_error("update_patients_wc: updated no change? $updated[first_name] $updated[last_name] $updated[birth_date] cp:$updated[patient_id_cp] wc:$updated[patient_id_wc]", $updated);

    if ($updated['patient_inactive'] !== $updated['old_patient_inactive']) {
      $patient = find_patient($mysql, $updated)[0];

      echo "\nWC Patient Inactive Status Changed $updated[first_name] $updated[last_name] $updated[birth_date] $updated[old_patient_inactive] >>> $updated[patient_inactive]";

      update_cp_patient_active_status($mssql, $patient['patient_id_cp'], $updated['patient_inactive']);

      log_notice("WC Patient Inactive Status Changed", $updated);
    }

    if ($updated['email'] !== $updated['old_email']) {

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

        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_address1', $updated['old_patient_address1']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_address2', $updated['old_patient_address2']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_city', $updated['old_patient_city']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_state', $updated['old_patient_state']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_zip', $updated['old_patient_zip']);

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
      wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'coupon', $updated['old_payment_coupon'] ?: $updated['old_tracking_coupon']);
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

      wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'backup_pharmacy', json_encode([
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
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['MAIL']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['AUTOPAY'])
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['AUTOPAY']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['ONLINE'])
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['ONLINE']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['COUPON'])
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['COUPON']);

      else if ($updated['old_payment_method_default'] == PAYMENT_METHOD['CARD EXPIRED'])
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['CARD EXPIRED']);

      else
        log_error("NOT SURE WHAT TO DO FOR PAYMENT METHOD $updated");
    }

    if ( ! $updated['first_name'] OR ! $updated['first_name'] OR ! $updated['birth_date']) {

      log_error("Patient Set Incorrectly", [$changed, $updated]);

    } else if (
      $updated['first_name'] !== $updated['old_first_name'] OR
      $updated['last_name'] !== $updated['old_last_name'] OR
      $updated['birth_date'] !== $updated['old_birth_date'] OR
      $updated['language'] !== $updated['old_language']
    ) {

      if (is_patient_match($mysql, $updated)) { //Make sure there is only one match on either side of the

        //TODO What is the source of truth if there is a mismatch?  Do we update CP to match WC or vice versa?
        //For now, think patient should get to decide.  Provider having wrong/different name will be handled by name matching algorithm

        //Important for a "'" in names
        $first_name = escape_db_values($updated['first_name']);
        $last_name  = escape_db_values($updated['last_name']);

        $sp = "EXEC SirumWeb_AddUpdatePatient '$first_name', '$last_name', '$updated[birth_date]', '$updated[phone1]', '$updated[language]'";
        log_notice("Patient Name/Identity Updated.  If called repeatedly there is likely a two matching CP users", [$sp, $changed]);

        upsert_patient_cp($mssql, $sp);

        //wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'first_name', $updated['old_first_name']);
        //wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'last_name', $updated['old_last_name']);
        //wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'birth_date', $updated['old_birth_date']);
        //wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'language', $updated['old_language']);
      } else {

        echo "\nupdate_patients_wc: patient name changed but now there are multiple matches";
        SirumLog::alert(
          "update_patients_wc: patient name changed but now there are multiple matches",
          [
            'updated' => $updated,
            'changed' => $changed
          ]
        );
      }
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

      if (
        $updated['allergies_other'] !== $updated['old_allergies_other'] AND
        strlen($updated['allergies_other']) > 0 AND
        strlen($updated['allergies_other']) == strlen($updated['old_allergies_other'])
      )
        SirumLog::alert('Trouble saving allergies_other.  Most likely an encoding issue', $changed);

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
      $sql = "EXEC SirumWeb_AddRemove_Allergies '$updated[patient_id_cp]', '$allergies'";

      if ($allergies) {
        //echo "\n$sql";
        $res = upsert_patient_cp($mssql, $sql);

      } else {

        $err = [$sql, $res, json_last_error_msg(), $allergy_array];
        print_r($err);
        log_error("update_patients_wc: SirumWeb_AddRemove_Allergies failed", $err);

      }
    }

    if ($updated['medications_other'] !== $updated['old_medications_other']) {

      if (strlen($updated['medications_other']) > 0 AND strlen($updated['medications_other']) == strlen($updated['old_medications_other']))
        SirumLog::alert('Trouble saving medications_other.  Most likely an encoding issue', $changed);

      export_cp_patient_save_medications_other($mssql, $updated);
    }
  }

  log_timer('patients-wc-updated', $loop_timer, $count_updated);


  SirumLog::resetSubroutineId();
}
