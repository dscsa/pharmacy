<?php

require_once 'helpers/helper_full_patient.php';
require_once 'helpers/helper_try_catch_log.php';

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

use GoodPill\Utilities\Timer;
use GoodPill\Models\GpPatient;

/**
 * Handle all the possible changes to Carepoint Patiemnts
 * @param  array $changes  An array of arrays with deledted, created, and
 *      updated elements
 * @return void
 */
function update_patients_cp(array $changes) : void
{

    // Make sure we have some data
    $change_counts = [];
    foreach (array_keys($changes) as $change_type) {
        $change_counts[$change_type] = count($changes[$change_type]);
    }

    if (array_sum($change_counts) == 0) {
       return;
    }

    GPLog::info(
        "update_patients_cp: changes",
        $change_counts
    );

    GPLog::notice('data-update-patients-cp', $changes);
    if (isset($changes['updated'])) {
        Timer::start('update.patients.cp.updated');
        foreach ($changes['updated'] as $i => $updated) {
            cp_patient_updated($updated);
        }
        Timer::stop('update.patients.cp.updated');
    }


    /*
     TODO Upsert WooCommerce Patient Info
     TODO Upsert Salseforce Patient Info
     TODO Consider Pat_Autofill Implications
     TODO Consider Changing of Payment Method
    */
}


/*

    Change Handlers

 */

/**
 * Handle and updated cp patients
 * @param  array $updated  The data that is updated
 * @return null|array      The updated data or null if returned early
 *
 */
function cp_patient_updated(array $updated) : ?array
{
    GPLog::$subroutine_id = "patients-cp-updated-".sha1(serialize($updated));
    GPLog::info("data-patients-cp-updated", ['updated' => $updated]);
    GPLog::debug(
        "update_patients_cp: Carepoint PATIENT Updated",
        [
             'Updated' => $updated,
             'source'  => 'CarePoint',
             'type'    => 'patients',
             'event'   => 'updated'
         ]
    );

    $GpPatient = GpPatient::where('patient_id_cp', $updated['patient_id_cp'])->first();
    $GpPatient->setChanges($updated);

    $mysql   = new Mysql_Wc();
    $mssql   = new Mssql_Cp();
    $changed = changed_fields($updated);

    // TODO Change this code to not call load_full_patient
    // NOTE Patient regististration will change it from 0 -> 1)
    if ($GpPatient->hasFieldChanged('patient_autofill')) {
        // Order hasn't shipped then handle
        // This updates & overwrites set_rx_messages
        // TODO Currently we are useing the add_full_fields function that
        // is called by the load_full_patient.  We need to update this
        // method to actually do the work
        $GpPatient->recalculateRxMessages();

        $log_mesage = sprintf(
            "An %s patient autofill setting has changed to %s",
            ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient',
            $updated['patient_autofill']
        );

        AuditLog::log($log_mesage, $updated);

        GPLog::notice(
            "update_patient_cp patient_autofill changed.  Confirm correct updated rx_messages",
            [
                 'patient' => $patient,
                 'updated' => $updated,
                 'changed' => $changed,
                 'is_new'  => ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient'
             ]
        );
    }

    if ($GpPatient->hasFieldChanged('refills_used')) {
        GPLog::notice(
            "Patient updated in CP",
            [
                 'updated' => $updated,
                 'changed' => $changed,
                 'is_new'  => ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient'
             ]
        );
    }

    // The patients secondary phone numbe has changed or bee deleted
    if (!$GpPatient->phone2) {
        //Phone deleted in CP so delete in WC
        AuditLog::log("Phone2 deleted for patient via CarePoint", $GpPatient->attributesToArray());
        GPLog::warning(
            "Phone2 deleted in CP",
            [
                 'updated' => $updated,
                 'patient' => $GpPatient->toArray()
             ]
        );

        $GpPatient->updateWpMeta('billing_phone', null);

    } elseif ($GpPatient->phone2 == $GpPatient->phone1) {
        AuditLog::log("Phone2 deleted for patient via CarePoint, Copying data to WooCommerce", $updated);
        $GpPatient->deletePhoneFromCarepoint(9);

    } elseif ($GpPatient->hasFieldChanged('phone2')) {
        GPLog::notice(
            "Phone2 updated in CarePoint, Copying data to WooCommerce",
            ['patient_id_cp' => $gpPatient->patient_id_cp]
        );

        AuditLog::log("Phone2 changed for patient via CarePoint", $GpPatient->attributesToArray());

        $GpPatient->updateWpMeta('billing_phone', $GpPatient->hasFieldChanged('phone2'));
    }

    //  The primary phone number for the patient has changed
    if ($GpPatient->hasFieldChanged('phone1')) {
        AuditLog::log("Phone1 changed for patient via CarePoint, Copying data to WooCommerce", $updated);
        GPLog::notice(
            "Phone1 updated in CP, Copying data to WooCommerce",
            ['patient_id_cp' => $gpPatient->patient_id_cp]
        );
    }

    // The patient status has changed
    if ($GpPatient->hasFieldChanged('patient_inactive')) {

        GPLog::notice("CP Patient Inactive Status Changed", ['updated' => $GpPatient->getChanges()]);

        AuditLog::log(
            "Patient status changed to {$GpPatient->patient_inactive} via CarePoint",
            $GpPatient->attributesToArray()
        );

        $GpPatient->setWcActiveStatus();
    }

    if (
        $GpPatient->hasFieldChanged('payment_method_default')
        && $GpPatient->payment_method_default != PAYMENT_METHOD['AUTOPAY']
    ) {
        AuditLog::log("Autopay has been disabled via CarePoint", $GpPatient->getChanges());
        GPLog::info("Canceling 'Autopay Reminders' because patient updated payment_method_default");

        $gpPatient->cancelEvents(['Autopay Reminder']);
    }

    if ($GpPatient->hasFieldChanged('payment_method_default')
        && isset($GpPatient->payment_card_last4)) {
        AuditLog::log("Patient has updated credit card details via CarePoint", $GpPatient->getChanges());

        GPLog::warning(
            sprintf(
                "Need to replace Card Last4 in Autopay Reminder %s %s >>> %s, %s >>> %s %s",
                $updated['payment_method_default'],
                $updated['old_payment_card_type'],
                $updated['payment_card_type'],
                $updated['old_payment_card_last4'],
                $updated['payment_card_last4'],
                $updated['payment_card_date_expired']
            ),
            ['updated' => $GpPatient->getChanges()]
        );

        $GpPatient->updateEvents('Autopay Reminder', 'last4', $this->last4);

        // Probably by generalizing the code the currently removes drugs from the refill reminders.
        // TODO Autopay Reminders (Remove Card, Card Expired, Card Changed, Order Paid Manually)
    }

    if ($GpPatient->hasAnyFieldChanged(
        [
            'first_name',
            'last_name',
            'birth_date'
        ]
    )) {
        if (isset($GpPatient->patient_id_wc)) {
            // NOTICE We intentionally no longer push these changes to woocommerce
            // wc_update_patient($patient);
            AuditLog::log(
                sprintf(
                    "Patient identifying fields have been updated in Carepoint First Name: %s,
                    Last name: %s, Birth Date: %s, Language %s.  We no longer push these changes
                    over to Woocommerce",
                    $GpPatient->first_name,
                    $GpPatient->last_name,
                    $GpPatient->birth_date,
                    $GpPatient->language
                ),
                $updated
            );
        }
    }

    GPLog::resetSubroutineId();
    return $updated;
}
