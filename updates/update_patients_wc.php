<?php

require_once 'changes/changes_to_patients_wc.php';
require_once 'exports/export_wc_patients.php';
require_once 'exports/export_cp_patients.php';

use Sirum\Logging\SirumLog;

function update_patients_wc()
{
    $changes = changes_to_patients_wc("gp_patients_wc");

    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    if (! $count_deleted and ! $count_created and ! $count_updated) {
        return;
    }

    SirumLog::debug(
        "update_patients_wc counts",
        [
          'deleted' => $count_deleted,
          'created' => $count_created,
          'updated' => $count_updated
        ]
    );

    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    function name_mismatch($new, $old)
    {
        $new = str_replace(['-'], [' '], $new);
        $old = str_replace(['-'], [' '], $old);
        return stripos($new, $old) === false and stripos($old, $new) === false;
    }

    $created_mismatched = 0;
    $created_matched    = 0;
    $created_needs_form = 0;
    $created_new_to_cp  = 0;

    foreach ($changes['created'] as $created) {
        $patient = find_patient_wc($mysql, $created);

        if (! empty($patient[0]['patient_id_wc'])) {
            $created_mismatched++;
            SirumLog::alert(
                'mismatched patient_id_wc or duplicate wc patient registration',
                [
                  "created" => $created,
                  "patient" => $patient[0]
                ]
            );
            log_error('update_patients_wc: mismatched patient_id_wc or duplicate wc patient registration?', [$created, $patient[0]]);
        } elseif (! empty($patient[0]['patient_id_cp'])) {
            $created_matched++;
            match_patient_wc($mysql, $created, $patient[0]['patient_id_cp']);
        } elseif (! $created['pharmacy_name']) {
            //Registration Started but Not Complete (first 1/2 of the registration form)
            $created_needs_form++;
        } elseif ($created['patient_state'] != 'GA') {
            // Not In GA - Not sure why empty
        } else {
            $created_new_to_cp++;
            $created_date = "Created:".date('Y-m-d H:i:s');

            $salesforce = [
              "subject"   => "Fix Duplicate Patient",
              "body"      => "Patient $created[first_name] $created[last_name] $created[birth_date] (WC user_id:$created[patient_id_wc]) in WC but not in CP. Fix and notify patient if necessary. Likely #1 a duplicate user in WC (forgot login so reregistered with slightly different name or DOB), #2 patient inactivated in CP (remove their birthday in WC to deactivate there), or #3 inconsistent birth_date between Rx in CP and Registration in WC. $created_date",
              "contact"   => "$created[first_name] $created[last_name] $created[birth_date]",
              "assign_to" => "Joseph",
              "due_date"  => date('Y-m-d')
            ];

            $event_title = "$salesforce[subject]: $salesforce[contact] $created_date";

            //In WC Patient Changes we don't have the "patient_date_added" or "patient_date_changed" CP fields,
            //"patient_date_updated" will always be within past 10mins, so use "patient_date_registered"
            $secs = time() - strtotime($created['patient_date_registered']);

            if ($secs/60 < 30) { //Otherwise gets repeated every 10mins.
                create_event($event_title, [$salesforce]);
                log_error("New $event_title", [$secs, $patient, $created]);
            } elseif (date('h') == '11') { //Twice a day so use a lower case h for 12 hour clock instead of 24 hour.
                log_error("Old $event_title", [$secs, $patient, $created]);
            }
        }
    }

    SirumLog::debug(
        'created counts',
        [
          'created_mismatched' => $created_mismatched,
          'created_matched'    => $created_matched,
          'created_needs_form' => $created_needs_form,
          'created_new_to_cp'  => $created_new_to_cp
        ]
    );

    foreach ($changes['deleted'] as $i => $deleted) {
      //Dummy accounts that have been cleared out of WC
        if (stripos($deleted['first_name'], 'Test') !== false or stripos($deleted['first_name'], 'User') !== false or stripos($deleted['email'], 'user') !== false or stripos($deleted['email'], 'test') !== false) {
            continue;
        }

        if ($deleted['patient_id_wc']) {
            log_error('update_patients_wc deleted: patient was just deleted from WC', $deleted);
        }
    }

    foreach ($changes['updated'] as $i => $updated) {
        // There isn't a carepoint id, so we should find
        // the match and start over next time
        if (! $updated['patient_id_cp']) {
            $patient = find_patient_wc($mysql, $updated);
            match_patient_wc($mysql, $updated, $patient[0]['patient_id_cp']);
            continue;
        }

        $changed = changed_fields($updated);

        if ($changed) {
            SirumLog::debug(
                "update_patients_wc: updated changed",
                [
                  'first_name'     => $updated['first_name'],
                  'last_name'      => $updated['last_name'],
                  'birth_date'     => $updated['birth_date'],
                  'carepoint_id'   => $updated['patient_id_cp'],
                  'woocommerce_id' => $updated['patient_id_wc'],
                  'changed'        => $changed
                ]
            );
        } else {
            SirumLog::info(
                "update_patients_wc: updated no change?",
                [
                  'first_name'     => $updated['first_name'],
                  'last_name'      => $updated['last_name'],
                  'birth_date'     => $updated['birth_date'],
                  'carepoint_id'   => $updated['patient_id_cp'],
                  'woocommerce_id' => $updated['patient_id_wc'],
                  'updated'        => $updated
                ]
            );
        }

        if (! $updated['email'] and $updated['old_email']) {
            upsert_patient_wc($mysql, $updated['patient_id_wc'], 'email', $updated['old_email']);
        } elseif ($updated['email'] !== $updated['old_email']) {
            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatEmail '$updated[patient_id_cp]', '$updated[email]'");
        }

        if ((! $updated['patient_address1'] and $updated['old_patient_address1']) or
            (! $updated['patient_address2'] and $updated['old_patient_address2']) or
            (! $updated['patient_city'] and $updated['old_patient_city']) or
            (strlen($updated['patient_state']) != 2 and strlen($updated['old_patient_state']) == 2) or
            (strlen($updated['patient_zip']) != 5 and strlen($updated['old_patient_zip']) == 5)
        ) {
            upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_address1', $updated['old_patient_address1']);
            upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_address2', $updated['old_patient_address2']);
            upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_city', $updated['old_patient_city']);
            upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_state', $updated['old_patient_state']);
            upsert_patient_wc($mysql, $updated['patient_id_wc'], 'patient_zip', $updated['old_patient_zip']);
        } elseif ($updated['patient_address1'] !== $updated['old_patient_address1'] or
                  $updated['patient_address2'] !== $updated['old_patient_address2'] or
                  $updated['patient_city'] !== $updated['old_patient_city'] or
                  (strlen($updated['patient_state']) == 2 and $updated['patient_state'] !== $updated['old_patient_state']) or
                  (strlen($updated['patient_zip']) == 5 and $updated['patient_zip'] !== $updated['old_patient_zip'])
        ) {
            $address1 = escape_db_values($updated['patient_address1']);
            $address2 = escape_db_values($updated['patient_address2']);
            $city = escape_db_values($updated['patient_city']);

            $address3 = 'NULL';
            if ($updated['patient_state'] != 'GA') {
                log_notice("$updated[first_name] $updated[last_name] $updated[birth_date]");
                $address3 = "'!!!! WARNING NON-GEORGIA ADDRESS !!!!'";
            }

            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatHomeAddr '$updated[patient_id_cp]', '$address1', '$address2', $address3, '$city', '$updated[patient_state]', '$updated[patient_zip]', 'US'");
        }

        //NOTE: Different/Reverse logic here. Deleting in CP should save back into WC
        if (($updated['payment_coupon'] and ! $updated['old_payment_coupon']) or
            ($updated['tracking_coupon'] and ! $updated['old_tracking_coupon'])
        ) {
            $user_def4 = "$updated[payment_card_last4],$updated[payment_card_date_expired],$updated[payment_card_type],".($updated['payment_coupon'] ?: $updated['tracking_coupon']);
            echo json_encode($updated, JSON_PRETTY_PRINT) . "\n";
            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '4', '$user_def4'");
        } elseif ($updated['payment_coupon'] !== $updated['old_payment_coupon'] or //Still allow for deleteing coupons in CP
                  $updated['tracking_coupon'] !== $updated['old_tracking_coupon'] //Still allow for deleteing coupons in CP
        ) {
            upsert_patient_wc($mysql, $updated['patient_id_wc'], 'coupon', $updated['old_payment_coupon'] ?: $updated['old_tracking_coupon']);
        }

        if (! $updated['phone1'] and $updated['old_phone1']) {
            //Phone was deleted in WC, so delete in CP
            delete_cp_phone($mssql, $updated['patient_id_cp'], 6);
        } elseif (strlen($updated['phone1']) < 10 and strlen($updated['old_phone1']) >= 10) {
            //Phone added to WC was malformed, so revert to old phone
            update_wc_phone1($mysql, $updated['patient_id_wc'], $updated['old_phone1']);
        } elseif ($updated['phone1'] !== $updated['old_phone1']) {
            //Well-formed added to WC so now add to CP
            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone1]'");
        }

        if (! $updated['phone2'] and $updated['old_phone2']) {
            //Phone was deleted in WC, so delete in CP
            delete_cp_phone($mssql, $updated['patient_id_cp'], 9);
        } elseif ($updated['phone2'] and $updated['phone2'] == $updated['phone1']) {
            //Phone added to WC was a duplicate
            update_wc_phone2($mysql, $updated['patient_id_wc'], null);
        } elseif (strlen($updated['phone2']) < 10 and strlen($updated['old_phone2']) >= 10) {
            //Phone added to WC was malformed, so revert to old phone
            update_wc_phone2($mysql, $updated['patient_id_wc'], $updated['old_phone2']);
        } elseif ($updated['phone2'] !== $updated['old_phone2']) {
            //Well-formed, non-duplicated phone added to WC so now add to CP
            log_error("EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone2]', 9", $updated);
            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone2]', 9");
        }

        //If pharmacy name changes then trust WC over CP
        if ($updated['pharmacy_name'] and $updated['pharmacy_name'] !== $updated['old_pharmacy_name']) {
            $user_def1 = escape_db_values($updated['pharmacy_name']);
            $user_def2 = substr("$updated[pharmacy_npi],$updated[pharmacy_fax],$updated[pharmacy_phone],$updated[pharmacy_address]", 0, 50);

            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '1', '$user_def1'");
            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '2', '$user_def2'");
        } elseif ($updated['pharmacy_npi'] !== $updated['old_pharmacy_npi'] or
                  $updated['pharmacy_fax'] !== $updated['old_pharmacy_fax'] or
                  $updated['pharmacy_phone'] !== $updated['old_pharmacy_phone']
        ) {
            // old_pharamcy address is not populated since
            // we only save a partial address in CP so will always differ
            upsert_patient_wc(
                $mysql,
                $updated['patient_id_wc'],
                'backup_pharmacy',
                json_encode([
                  'name'   => escape_db_values($updated['old_pharmacy_name']),
                  'npi'    => $updated['old_pharmacy_npi'],
                  'fax'    => $updated['old_pharmacy_fax'],
                  'phone'  => $updated['old_pharmacy_phone'],
                  'street' => $updated['pharmacy_address']
                ])
            );
        }

        if ($updated['payment_method_default'] and ! $updated['old_payment_method_default']) {
            upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '3', '$updated[payment_method_default]'");
        } elseif ($updated['payment_method_default'] !== $updated['old_payment_method_default']) {
            SirumLog::debug(
                'update_patients_wc: updated payment_method_default. Deleting Autopay Reminders',
                ['updated_details' => $updated]
            );
            if ($updated['old_payment_method_default'] == PAYMENT_METHOD['MAIL']) {
                upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['MAIL']);
            } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['AUTOPAY']) {
                upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['AUTOPAY']);
            } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['ONLINE']) {
                upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['ONLINE']);
            } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['COUPON']) {
                upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['COUPON']);
            } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['CARD EXPIRED']) {
                upsert_patient_wc($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['CARD EXPIRED']);
            } else {
                SirumLog::alert(
                    "update_patients_wc: Payment method updated and not sure what to do",
                    ['updated_details' => $updated]
                );
            }
        }

        if (! $updated['first_name'] or ! $updated['lastt_name'] or ! $updated['birth_date']) {
            SirumLog::alert(
                'Patient Set Incorrectly, missing first_name or last_name or birthdate',
                [
                 'changed' => $changed,
                 'updated' => $updated
                ]
            );
        } elseif (name_mismatch($updated['first_name'], $updated['old_first_name']) or
            name_mismatch($updated['last_name'], $updated['old_last_name'])
        ) {
            SirumLog::alert(
                'Patient Name Misspelled or Identity Changed?',
                ["patient" => $updated]
            );
        } elseif ($updated['first_name'] !== $updated['old_first_name'] or
                  $updated['last_name'] !== $updated['old_last_name'] or
                  $updated['birth_date'] !== $updated['old_birth_date'] or
                  $updated['language'] !== $updated['old_language']
                 ) {
            $sp = "EXEC SirumWeb_AddUpdatePatient '{$updated['first_name']}', " .
                  "'{$updated['last_name']}', '{$updated['birth_date']}', " .
                  "'{$updated['phone1']}', '{$updated['language']}'";

            SirumLog::notice(
                "Patient Name/Identity Updated.  If called repeatedly
                there is likely a two matching CP users",
                [
                  "stored_procedure" => $sp,
                  "changed_patient"  => $changed
                ]
            );

            upsert_patient_cp($mssql, $sp);
        }


        /*
         * If the patient has updated any of their alergy fields, We need to
         * copy those updates over to Carepoint
         *
         * NOTE CarePoint Update Triggered
         */
        if ($updated['allergies_none'] !== $updated['old_allergies_none'] or
            $updated['allergies_aspirin'] !== $updated['old_allergies_aspirin'] or
            $updated['allergies_amoxicillin'] !== $updated['old_allergies_amoxicillin'] or
            $updated['allergies_azithromycin'] !== $updated['old_allergies_azithromycin'] or
            $updated['allergies_cephalosporins'] !== $updated['old_allergies_cephalosporins'] or
            $updated['allergies_codeine'] !== $updated['old_allergies_codeine'] or
            $updated['allergies_erythromycin'] !== $updated['old_allergies_erythromycin'] or
            $updated['allergies_nsaids'] !== $updated['old_allergies_nsaids'] or
            $updated['allergies_penicillin'] !== $updated['old_allergies_penicillin'] or
            $updated['allergies_salicylates'] !== $updated['old_allergies_salicylates'] or
            $updated['allergies_sulfa'] !== $updated['old_allergies_sulfa'] or
            $updated['allergies_tetracycline'] !== $updated['old_allergies_tetracycline'] or
            $updated['allergies_other'] !== $updated['old_allergies_other']
          ) {
            $allergy_array = [
              'allergies_none'           => $updated['allergies_none'] ?: '',
              'allergies_aspirin'        => $updated['allergies_aspirin'] ?: '',
              'allergies_amoxicillin'    => $updated['allergies_amoxicillin'] ?: '',
              'allergies_azithromycin'   => $updated['allergies_azithromycin'] ?: '',
              'allergies_cephalosporins' => $updated['allergies_cephalosporins'] ?: '',
              'allergies_codeine'        => $updated['allergies_codeine'] ?: '',
              'allergies_erythromycin'   => $updated['allergies_erythromycin'] ?: '',
              'allergies_nsaids'         => $updated['allergies_nsaids'] ?: '',
              'allergies_penicillin'     => $updated['allergies_penicillin'] ?: '',
              'allergies_salicylates'    => $updated['allergies_salicylates'] ?: '',
              'allergies_sulfa'          => $updated['allergies_sulfa'] ?: '',
              'allergies_tetracycline'   => $updated['allergies_tetracycline'] ?: '',
              'allergies_other'          => escape_db_values($updated['allergies_other'])
            ];

            $allergies = json_encode(utf8ize($allergy_array), JSON_UNESCAPED_UNICODE);

            if ($allergies) {
                $res = upsert_patient_cp(
                    $mssql,
                    "EXEC SirumWeb_AddRemove_Allergies '{$updated['patient_id_cp']}', '{$allergies}'"
                );
            } else {
                log_error("update_patients_wc: EXEC SirumWeb_AddRemove_Allergies '$updated[patient_id_cp]', '$allergies'", [$res, json_last_error_msg(), $allergy_array]);
            }
        }

        /*
         * If the user has an updated medications_other record in WooCommerce,
         * we should update CarePoint
         *
         * NOTE CarePoint Update Triggered
         */
        if ($updated['medications_other'] !== $updated['old_medications_other']) {
            $patient = find_patient_wc($mysql, $updated);

            if (@$patient['patient_note']) {
                echo "Patient Note: $patient[patient_note]";
            }

            export_cp_patient_save_medications_other($mssql, $updated);
        }
    }
}
