<?php

require_once 'exports/export_wc_patients.php';
require_once 'exports/export_cp_patients.php';
require_once 'helpers/helper_matching.php';
require_once 'helpers/helper_calendar.php';

use Sirum\Logging\{
    SirumLog,
    AuditLog,
    CliLog
};

use Sirum\Storage\Goodpill;
use Sirum\Utilities\Timer;


/**
 * Handle all the possible changes to WooCommerce Patiemnts
 * @param  array $changes  An array of arrays with deledted, created, and
 *      updated elements
 * @return void
 */
function update_patients_wc(array $changes) : void
{
    SirumLog::notice('data-update-patients-wc', $changes);

    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";

    SirumLog::notice(
        "update_patients_wc: all changes. {$msg}",
        [
            'deleted_count' => $count_deleted,
            'created_count' => $count_created,
            'updated_count' => $count_updated
        ]
    );

    CliLog::notice($msg);

    if ($count_deleted + $count_created + $count_updated == 0) {
        return;
    }


    Timer::start('update.patients.wc.created');
    foreach ($changes['created'] as $created) {
        wc_patient_created($created);
    }
    Timer::stop('update.patients.wc.created');

    Timer::start('update.patients.wc.deleted');
    foreach ($changes['deleted'] as $i => $deleted) {
        wc_patient_deleted($deleted);
    }
    Timer::stop('update.patients.wc.deleted');

    Timer::start('update.patients.wc.updated');
    foreach ($changes['updated'] as $i => $updated) {
        wc_patient_updated($updated);
    }
    Timer::stop('update.patients.wc.updated');
}

/*

    Change Handlers

 */

/**
 * Handled when a patient is created
 * @param  array  $created The changes for the created patient
 * @return null|array      Return the original created when we complete the function
 */
function wc_patient_created(array $created)
{
    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    SirumLog::$subroutine_id = "patients-wc-created-".sha1(serialize($created));
    SirumLog::info("data-patients-wc-created", ['created' => $created]);

    // Overrite Rx Messages everytime a new order created otherwise
    // same message would stay for the life of the Rx

    AuditLog::log("Patient created via Patient Portal", $created);
    SirumLog::debug(
        "update_patients_wc: WooCommerce PATIENT Created $created[first_name] $created[last_name] $created[birth_date]",
        [
            'created' => $created,
            'source'  => 'WooCommerce',
            'type'    => 'patients',
            'event'   => 'created'
        ]
    );

    if (!$created['pharmacy_name']) {
        $limit = 24; //Delete Incomplete Registrations after 24 hours
        $hours = round((time() - strtotime($created['patient_date_registered']))/60/60, 1);

        if ($hours > $limit) {
            AuditLog::log(
                "Registration is incomplete and will be deleted because it
                is older than {$limit} hours",
                $created
            );
            SirumLog::debug(
                "update_patients_wc: deleting incomplete registration
                 for $created[first_name] $created[last_name] $created[birth_date]
                 after $limit hours ",
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
        return null;
    }

    $is_match = is_patient_match($mysql, $created);

    if ($is_match) {
        match_patient($mysql, $is_match['patient_id_cp'], $is_match['patient_id_wc']);
    }

    SirumLog::resetSubroutineId();
    return $created;
}

/**
 * Handled when a patient is updated
 * @param  array  $created The changes for the updated patient
 * @return null|array      Return the original updated when we complete the function
 */
function wc_patient_updated(array $updated)
{
    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    SirumLog::$subroutine_id = "patients-wc-updated-".sha1(serialize($updated));
    SirumLog::info("data-patients-wc-updated", ['updated' => $updated]);

    $changed = changed_fields($updated);

    AuditLog::log(
        "Registration has been updated by Patient Portal",
        $updated
    );

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

    $update_message = sprintf(
        "update_patients_wc: registration %s %s %s %s cp:%s wc:%s",
        ($changed) ? 'changed' : 'no change?',
        $updated['first_name'],
        $updated['last_name'],
        $updated['birth_date'],
        $updated['patient_id_cp'],
        $updated['patient_id_wc']
    );

    if ($changed) {
        SirumLog::debug($update_message, [ 'changed' => $changed ]);
    } else {
        SirumLog::error($update_message, [ 'updated' => $updated ]);
    }

    if (! $updated['patient_id_cp']) {
        $is_match = is_patient_match($mysql, $updated);

        if ($is_match) {
            match_patient($mysql, $is_match['patient_id_cp'], $is_match['patient_id_wc']);
        }

        return null;
    }

    if ($updated['patient_inactive'] !== $updated['old_patient_inactive']) {
        $patient = find_patient($mysql, $updated)[0];

        AuditLog::log(
            "Patients inactive has changed from {$updated['old_patient_inactive']}
             to {$updated['patient_inactive']} via the Patient Porta",
            $updated
        );

        update_cp_patient_active_status($mssql, $patient['patient_id_cp'], $updated['patient_inactive']);

        SirumLog::notice("WC Patient Inactive Status Changed", ['updated' => $updated]);
    }

    if ($updated['email'] !== $updated['old_email']) {
        upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatEmail '$updated[patient_id_cp]', '$updated[email]'");
    }

    if ((! $updated['patient_address1'] and $updated['old_patient_address1']) or
        (! $updated['patient_address2'] and $updated['old_patient_address2']) or
        (! $updated['patient_city'] and $updated['old_patient_city']) or
        (strlen($updated['patient_state']) != 2 and strlen($updated['old_patient_state']) == 2) or
        (strlen($updated['patient_zip']) != 5 and strlen($updated['old_patient_zip']) == 5)
    ) {
        AuditLog::log(
            sprintf(
                "Patient address has been updated via Patient Portal.  %s %s, %s, %s  %s",
                $updated['patient_address1'],
                $updated['patient_address2'],
                $updated['patient_city'],
                $updated['patient_state'],
                $updated['patient_zip']
            ),
            $updated
        );

        SirumLog::notice(
            "update_patients_wc: adding address. $updated[first_name] $updated[last_name] $updated[birth_date]",
            ['changed' => $changed, 'updated' => $updated]
        );

        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_address1', $updated['old_patient_address1']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_address2', $updated['old_patient_address2']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_city', $updated['old_patient_city']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_state', $updated['old_patient_state']);
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'patient_zip', $updated['old_patient_zip']);
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
            AuditLog::log(
                sprintf(
                    "!!!!WARNING!!!! Address changed to different state  %s",
                    $updated['patient_state']
                ),
                $updated
            );
            SirumLog::warning(
                "update_patients_wc: updated address-mismatch.
                $updated[first_name] $updated[last_name] $updated[birth_date]",
                [ 'updated' => $updated ]
            );
            $address3 = "'!!!! WARNING NON-GEORGIA ADDRESS !!!!'";
        }

        $sql = "EXEC SirumWeb_AddUpdatePatHomeAddr '$updated[patient_id_cp]', '$address1', '$address2', $address3, '$city', '$updated[patient_state]', '$updated[patient_zip]', 'US'";

        AuditLog::log(
            sprintf(
                "Patient address has been updated via Patient Portal.  %s %s, %s, %s  %s",
                $updated['patient_address1'],
                $updated['patient_address2'],
                $updated['patient_city'],
                $updated['patient_state'],
                $updated['patient_zip']
            ),
            $updated
        );

        SirumLog::notice(
            "update_patients_wc: updated address-mismatch. $updated[first_name]
            $updated[last_name] $updated[birth_date]",
            [
                'sql'     => $sql,
                'changed' => $changed,
                'updated' => $updated
            ]
        );
        upsert_patient_cp($mssql, $sql);
    }

    if ($updated['patient_date_registered'] != $updated['old_patient_date_registered']) {
        $sql = "UPDATE gp_patients
                    SET patient_date_registered = '{$updated['patient_date_registered']}'
                    WHERE patient_id_wc = {$updated['patient_id_wc']}";

        $mysql->run($sql);

        SirumLog::notice(
            "update_patients_wc: patient_registered. $updated[first_name]
            $updated[last_name] $updated[birth_date]",
            [ 'sql' => $sql ]
        );
    }

    // NOTE: Different/Reverse logic here. Deleting in CP should save back into WC
    if (($updated['payment_coupon'] and ! $updated['old_payment_coupon']) or
        ($updated['tracking_coupon'] and ! $updated['old_tracking_coupon'])
    ) {
        AuditLog::log(
            sprintf(
                "Patient payment updated via Patient Portal to  %s %s with payment coupon %s and tracking coupon %s",
                $updated['payment_card_type'],
                $updated['payment_card_last4'],
                $updated['payment_coupon'],
                $updated['tracking_coupon']
            ),
            $updated
        );
        $user_def4 = "$updated[payment_card_last4],$updated[payment_card_date_expired],$updated[payment_card_type],".($updated['payment_coupon'] ?: $updated['tracking_coupon']);
        upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '4', '$user_def4'");
    } elseif ($updated['payment_coupon'] !== $updated['old_payment_coupon'] or //Still allow for deleteing coupons in CP
        $updated['tracking_coupon'] !== $updated['old_tracking_coupon'] //Still allow for deleteing coupons in CP
    ) {
        AuditLog::log(
            sprintf(
                "Patient changed coupons via Patient Portal topayment coupon %s and tracking coupon %s",
                $updated['payment_coupon'],
                $updated['tracking_coupon']
            ),
            $updated
        );
        wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'coupon', $updated['old_payment_coupon'] ?: $updated['old_tracking_coupon']);
    }

    if (! $updated['phone1'] and $updated['old_phone1']) {
        //Phone was deleted in WC, so delete in CP
        AuditLog::log("Phone 1 deleted for patient via Patient Portal", $updated);
        delete_cp_phone($mssql, $updated['patient_id_cp'], 6);
    } elseif (strlen($updated['phone1']) < 10 and strlen($updated['old_phone1']) >= 10) {
        //Phone added to WC was malformed, so revert to old phone
        AuditLog::log("Phone 1 updated for patient via Patient Portal", $updated);
        update_wc_phone1($mysql, $updated['patient_id_wc'], $updated['old_phone1']);
    } elseif ($updated['phone1'] !== $updated['old_phone1']) {
        AuditLog::log("Phone 1 updated for patient via Patient Portal", $updated);
        //Well-formed added to WC so now add to CP
        upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone1]'");
    }

    if (! $updated['phone2'] and $updated['old_phone2']) {
        //Phone was deleted in WC, so delete in CP
        AuditLog::log("Phone 2 deleted for patient via Patient Portal", $updated);
        delete_cp_phone($mssql, $updated['patient_id_cp'], 9);
    } elseif ($updated['phone2'] and $updated['phone2'] == $updated['phone1']) {
        //Phone added to WC was a duplicate
        AuditLog::log("Phone 2 updated for patient via Patient Portal", $updated);
        update_wc_phone2($mysql, $updated['patient_id_wc'], null);
    } elseif (strlen($updated['phone2']) < 10 and strlen($updated['old_phone2']) >= 10) {
        //Phone added to WC was malformed, so revert to old phone
        AuditLog::log("Phone 2 updated for patient via Patient Portal", $updated);
        update_wc_phone2($mysql, $updated['patient_id_wc'], $updated['old_phone2']);
    } elseif ($updated['phone2'] !== $updated['old_phone2']) {
        //Well-formed, non-duplicated phone added to WC so now add to CP
        AuditLog::log("Phone 2 updated for patient via Patient Portal", $updated);
        upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatHomePhone '$updated[patient_id_cp]', '$updated[phone2]', 9");
    }

    //If pharmacy name changes then trust WC over CP
    if ($updated['pharmacy_name'] and $updated['pharmacy_name'] !== $updated['old_pharmacy_name']) {
        $user_def1 = escape_db_values($updated['pharmacy_name']);
        $user_def2 = substr("$updated[pharmacy_npi],$updated[pharmacy_fax],$updated[pharmacy_phone],$updated[pharmacy_address]", 0, 50);

        AuditLog::log(
            sprintf(
                "Backup Pharmacy updated to %s NPI# %s",
                $updated['pharmacy_name'],
                $updated['pharmacy_npi']
            ),
            $updated
        );

        upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '1', '$user_def1'");
        upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '2', '$user_def2'");
    } elseif ( //If pharmacy name is the same trust CP data over WC data so always update WC
        $updated['pharmacy_npi'] !== $updated['old_pharmacy_npi'] or
        $updated['pharmacy_fax'] !== $updated['old_pharmacy_fax'] or
        $updated['pharmacy_phone'] !== $updated['old_pharmacy_phone'] //OR
    ) {
        AuditLog::log(
            sprintf(
                "Backup Pharmacy updated to %s NPI# %s",
                $updated['old_pharmacy_name'],
                $updated['old_pharmacy_npi']
            ),
            $updated
        );

        // old_pharamcy address is not populated since We only save a
        // partial address in CP so will always differ
        wc_upsert_patient_meta(
            $mysql,
            $updated['patient_id_wc'],
            'backup_pharmacy',
            json_encode(
                [
                    'name' => escape_db_values($updated['old_pharmacy_name']),
                    'npi' => $updated['old_pharmacy_npi'],
                    'fax' => $updated['old_pharmacy_fax'],
                    'phone' => $updated['old_pharmacy_phone'],
                    'street' => $updated['pharmacy_address']
                ]
            )
        );
    }

    if ($updated['payment_method_default'] and ! $updated['old_payment_method_default']) {
        AuditLog::log("Patient add payment method via Patient Portal", $updated);
        upsert_patient_cp($mssql, "EXEC SirumWeb_AddUpdatePatientUD '$updated[patient_id_cp]', '3', '$updated[payment_method_default]'");
    } elseif ($updated['payment_method_default'] !== $updated['old_payment_method_default']) {
        SirumLog::warning(
            'update_patients_wc: updated payment_method_default. Deleting Autopay Reminders',
            ['updated' => $updated]
        );

        if ($updated['old_payment_method_default'] == PAYMENT_METHOD['MAIL']) {
            AuditLog::log("Patient payment method set to MAIL via Patient Portal", $updated);
            wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['MAIL']);
        } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['AUTOPAY']) {
            AuditLog::log("Patient payment method set to AUTOPAY via Patient Portal", $updated);
            wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['AUTOPAY']);
        } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['ONLINE']) {
            AuditLog::log("Patient payment method set to ONLINE via Patient Portal", $updated);
            wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['ONLINE']);
        } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['COUPON']) {
            AuditLog::log("Patient payment method set to COUPON via Patient Portal", $updated);
            wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['COUPON']);
        } elseif ($updated['old_payment_method_default'] == PAYMENT_METHOD['CARD EXPIRED']) {
            AuditLog::log("Patient's payment has expired", $updated);
            wc_upsert_patient_meta($mysql, $updated['patient_id_wc'], 'payment_method_default', PAYMENT_METHOD['CARD EXPIRED']);
        } else {
            AuditLog::log("Patient  payment method set to UNKNOWN via Patient Portal", $updated);
            SirumLog::error(
                "NOT SURE WHAT TO DO FOR PAYMENT METHOD",
                ['updated' => $updated]
            );
        }
    }

    if (!$updated['first_name'] or ! $updated['first_name'] or ! $updated['birth_date']) {
        SirumLog::error(
            "Patient Set Incorrectly",
            ['changed' => $changed, 'updated' => $updated]
        );
    } elseif (
        $updated['first_name'] !== $updated['old_first_name']
        || $updated['last_name'] !== $updated['old_last_name']
        || $updated['birth_date'] !== $updated['old_birth_date']
        || $updated['language'] !== $updated['old_language']
    ) {
        $is_patient_match = is_patient_match($mysql, $updated);
        if ($is_patient_match) {
            /*
                If we find a match, we should push this over to carepoint.
                Does this ever actually happen?  I don't think it does because
                at this point the users won't match
             */

            /*
                TODO What is the source of truth if there is a mismatch?
                Do we update CP to match WC or vice versa? For now, think patient
                should get to decide.  Provider having wrong/different name
                 will be handled by name matching algorithm
            */

            AuditLog::log(
                sprintf(
                    "Patient has updated identifying fields to
                     First Name: %s, Last name: %s, Birth Date: %s, Language %s",
                    $updated['first_name'],
                    $updated['last_name'],
                    $updated['birth_date'],
                    $updated['language']
                ),
                $updated
            );
            //Important for a "'" in names
            $first_name = escape_db_values($updated['first_name']);
            $last_name  = escape_db_values($updated['last_name']);

            $sp = "EXEC SirumWeb_AddUpdatePatient '$first_name', '$last_name', '$updated[birth_date]', '$updated[phone1]', '$updated[language]'";
            SirumLog::notice(
                "Patient Name/Identity Updated.  If called repeatedly
                there is likely a two matching CP users",
                [ 'sp' => $sp, 'changed' => $changed ]
            );

            upsert_patient_cp($mssql, $sp);
        } elseif (
            $updated['patient_id_cp']
            && $updated['patient_id_wc']
        ) {
            // This user has previously been matched, so we are going to
            // copy data from CP to WC
            $gpdb = GoodPill::getConnection();
            $pdo = $gpdb->prepare(
                "SELECT first_name,
                        last_name,
                        birth_date,
                        :patient_id_wc as patient_id_wc
                    FROM gp_patients_cp
                    WHERE patient_id_cp = :patient_id_cp
                "
            );

            $pdo->bindParam(':patient_id_cp', $updated['patient_id_cp'], \PDO::PARAM_STR);
            $pdo->bindParam(':patient_id_wc', $updated['patient_id_wc'], \PDO::PARAM_STR);
            $pdo->execute();

            if ($cp_patient = $pdo->fetch()) {
                SirumLog::notice(
                    "Forced Carepoint details onto WooCommerce user",
                    [
                        'updated'          => $updated,
                        'changed'          => $changed,
                        'cp_patient'       => $cp_patient,
                        'is_patient_match' => $is_patient_match
                      ]
                );

                wc_update_patient($cp_patient);
                $subject = "Changed patient name to match details from CarePoint";
                create_event(
                    $subject,
                    [
                         [
                             "subject"   => $subject,
                             "body"      => "We found a WooCommerce user that had previous been matched to Carepoint.
                                             Their WooCommerce identifiers didn't match, so we updated WooCommerce
                                             with the details from Carepoint.  Their previous WooCommerce username was:
                                             {$updated['old_first_name']} {$updated['old_last_name']} {$updated['old_birth_date']}.
                                             Their new WooCommerce username was:
                                             {$updated['first_name']} {$updated['last_name']} {$updated['birth_date']}.
                                             Their WooCommerce id is {$updated['patient_id_wc']} and their
                                             Carepoint ID is {$updated['patient_id_cp']}",
                             "contact"   => "{$updated['first_name']} {$updated['last_name']} {$updated['birth_date']}",
                             "assign_to" => "Kiah",
                             "due_date"  => date('Y-m-d')
                         ]
                    ]
                );
            }
        } else {
            $msg = "update_patients_wc: patient name changed but now count(matches) !== 1";
            SirumLog::critical(
                $msg,
                [
                    'updated'          => $updated,
                    'changed'          => $changed,
                    'is_patient_match' => $is_patient_match
                  ]
            );
        }
    } // END If key fields have changes

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
        if ($updated['allergies_other'] !== $updated['old_allergies_other'] and
            strlen($updated['allergies_other']) > 0 and
            strlen($updated['allergies_other']) == strlen($updated['old_allergies_other'])
        ) {
            AuditLog::log(
                "Patient tried to update alergies, but there was a problem saving the change",
                $updated
            );

            SirumLog::critical('Trouble saving allergies_other.  Most likely an encoding issue', $updated);
        }

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
            AuditLog::log(
                sprintf(
                    "Patient alergies updated via Patient Portal to %s",
                    implode(', ', $allergy_array)
                ),
                $updated
            );
            $res = upsert_patient_cp($mssql, $sql);
        } else {
            $err = [$sql, $res, json_last_error_msg(), $allergy_array];
            SirumLog::error("update_patients_wc: SirumWeb_AddRemove_Allergies failed", $err);
        }
    }

    if ($updated['medications_other'] !== $updated['old_medications_other']) {
        if (strlen($updated['medications_other']) > 0 and strlen($updated['medications_other']) == strlen($updated['old_medications_other'])) {
            SirumLog::critical('Trouble saving medications_other.  Most likely an encoding issue', $changed);
        }

        AuditLog::log(
            sprintf(
                "Patient other medications updated via Patient Portal to %s",
                $updated['medications_other']
            ),
            $updated
        );

        export_cp_patient_save_medications_other($mssql, $updated);
    }

    SirumLog::resetSubroutineId();
    return $updated;
}

/**
 * Handled when a patient is deleted
 * @param  array  $created The changes for the deleted patient
 * @return null|array      Return the original deleted when we complete the function
 */
function wc_patient_deleted(array $deleted)
{
    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    SirumLog::$subroutine_id = "patients-wc-deleted-".sha1(serialize($deleted));
    SirumLog::info("data-patients-wc-deleted", ['created' => $created]);

    $alert = [
      'deleted' => $deleted,
      'source'  => 'WooCommerce',
      'type'    => 'patients',
      'event'   => 'deleted'
    ];

    AuditLog::log(
        "Registration has been deleted via Patient Portal",
        $deleted
    );

    SirumLog::critical(
        "update_patients_wc: WooCommerce PATIENT deleted
        $deleted[first_name] $deleted[last_name] $deleted[birth_date]",
        ['alert' => $alert]
    );

    SirumLog::resetSubroutineId();
    return $deleted;
}
