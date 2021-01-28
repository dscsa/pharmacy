<?php

require_once 'helpers/helper_full_patient.php';

use Sirum\Logging\{
    SirumLog,
    AuditLog,
    CliLog
};

function update_patients_cp($changes)
{
    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
    echo $msg;

    SirumLog::notice(
        "update_patients_cp: all changes. {$msg}",
        [
            'deleted_count' => $count_deleted,
            'created_count' => $count_created,
            'updated_count' => $count_updated
        ]
    );

    if (! $count_deleted and ! $count_created and ! $count_updated) {
        return;
    }

    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    $loop_timer = microtime(true);

    foreach ($changes['updated'] as $i => $updated) {
        SirumLog::$subroutine_id = "patients-cp-updated-".sha1(serialize($updated));
        SirumLog::info("data-patients-cp-updated", ['updated' => $updated]);

        //Overrite Rx Messages everytime a new order created otherwis same
        //Omessage would stay for the life of the Rx
        SirumLog::debug(
            "update_patients_cp: Carepoint PATIENT Updated",
            [
                'Updated' => $updated,
                'source'  => 'CarePoint',
                'type'    => 'patients',
                'event'   => 'updated'
            ]
        );

        $changed = changed_fields($updated);

        //Patient regististration will change it from 0 -> 1)
        if ($updated['patient_autofill'] != $updated['old_patient_autofill']) {
             //This updates & overwrites set_rx_messages
             $patient = load_full_patient($updated, $mysql, true);
            $log_mesage = sprintf(
                "An %s patient autofill setting has changed to %s",
                ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient',
                $updated['patient_autofill']
            );

            AuditLog::log($log_mesage, $updated);

            SirumLog::notice(
                "update_patient_cp patient_autofill changed.  Confirm correct updated rx_messages",
                [
                    'patient' => $patient,
                    'updated' => $updated,
                    'changed' => $changed,
                    'is_new'  => ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient'
                ]
            );
        }

        if ($updated['refills_used'] == $updated['old_refills_used']) {
            SirumLog::notice(
                "Patient updated in CP",
                [
                'updated' => $updated,
                'changed' => $changed,
                'is_new'  => ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient'
              ]
            );
        }

        // The patients secondary phone numbe has changed or bee deleted
        if (! $updated['phone2'] and $updated['old_phone2']) {
            //Phone deleted in CP so delete in WC
            $patient = find_patient($mysql, $updated)[0];
            AuditLog::log("Phone2 deleted for patient via CarePoint", $patient);
            SirumLog::warning(
                "Phone2 deleted in CP",
                [
                    'updated' => $updated,
                    'patient' => $patient
                ]
            );
            update_wc_phone2($mysql, $patient['patient_id_wc'], null);
        } elseif (@$updated['phone2']
                  && $updated['phone2'] == $updated['phone1']) {
            AuditLog::log("Phone2 deleted for patient via CarePoint", $changed);
            //EXEC SirumWeb_AddUpdatePatHomePhone only inserts new phone numbers
            delete_cp_phone($mssql, $updated['patient_id_cp'], 9);
        } elseif ($updated['phone2'] !== $updated['old_phone2']) {
            $patient = find_patient($mysql, $updated)[0];
            SirumLog::notice(
                "Phone2 updated in CarePoint",
                [
                   'updated' => $updated,
                   'patient' => $patient
                ]
            );
            AuditLog::log("Phone2 changed for patient via CarePoint", $patient);
            update_wc_phone2($mysql, $patient['patient_id_wc'], $updated['phone2']);
        }

        //  The primary phone number for the patient has changed
        if ($updated['phone1'] !== $updated['old_phone1']) {
            AuditLog::log("Phone1 changed for patient via CarePoint", $changed);
            SirumLog::notice(
                "Phone1 updated in CP. Was this handled correctly?",
                ['updated' => $updated]
            );
        }

        // The patient status has changed
        if ($updated['patient_inactive'] !== $updated['old_patient_inactive']) {
            $patient = find_patient($mysql, $updated)[0];
            AuditLog::log("Patient status changed to {$updated['patient_inactive']} via CarePoint", $patient);
            update_wc_patient_active_status($mysql, $updated['patient_id_wc'], $updated['patient_inactive']);
            SirumLog::notice("CP Patient Inactive Status Changed", ['updated' => $updated]);
        }

        if ($updated['payment_method_default'] != PAYMENT_METHOD['AUTOPAY']
            && $updated['old_payment_method_default'] ==  PAYMENT_METHOD['AUTOPAY']) {
            AuditLog::log("Autopay has been disabled via CarePoint", $updated);
            cancel_events_by_person($updated['first_name'], $updated['last_name'], $updated['birth_date'], 'update_patients_wc: updated payment_method_default', ['Autopay Reminder']);
        }

        if ($updated['payment_card_last4']
            && $updated['old_payment_card_last4']
            && $updated['payment_card_last4'] !== $updated['old_payment_card_last4']) {
            AuditLog::log("Patient has updated credit card details via CarePoint", $updated);

            SirumLog::warning(
                sprintf(
                    "update_patients_wc: updated card_last4.  Need to replace Card"
                    . "Last4 in Autopay Reminder %s %s >>> %s, %s >>> %s %s",
                    $updated['payment_method_default'],
                    $updated['old_payment_card_type'],
                    $updated['payment_card_type'],
                    $updated['old_payment_card_last4'],
                    $updated['payment_card_last4'],
                    $updated['payment_card_date_expired']
                ),
                ['updated' => $updated]
            );

            update_last4_in_autopay_reminders(
                $updated['first_name'],
                $updated['last_name'],
                $updated['birth_date'],
                $updated['payment_card_last4']
            );

            // Probably by generalizing the code the currently removes drugs from the refill reminders.
            // TODO Autopay Reminders (Remove Card, Card Expired, Card Changed, Order Paid Manually)
        }

        if ($updated['first_name'] !== $updated['old_first_name']
            || $updated['last_name'] !== $updated['old_last_name']
            || $updated['birth_date'] !== $updated['old_birth_date']
        ) {
            $patient = load_full_patient($updated, $mysql);
            if (isset($patient['patient_id_wc'])) {
                wc_update_patient($patient);
                AuditLog::log(
                    sprintf(
                        "Patient identifying fields have been updated to
                         First Name: %s, Last name: %s, Birth Date: %s, Language %s",
                        $updated['first_name'],
                        $updated['last_name'],
                        $updated['birth_date'],
                        $updated['language']
                    ),
                    $updated
                );
            } else {

            }
        }
    }

    log_timer('patients-cp-updated', $loop_timer, $count_updated);

    SirumLog::resetSubroutineId();

    /*
     TODO Upsert WooCommerce Patient Info
     TODO Upsert Salseforce Patient Info
     TODO Consider Pat_Autofill Implications
     TODO Consider Changing of Payment Method
    */
}
