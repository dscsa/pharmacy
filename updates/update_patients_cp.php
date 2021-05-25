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
 * @param  array $changes An array of arrays with deledted, created, and
 *      updated elements.
 * @return void
 */
function update_patients_cp(array $changes) : void
{
    // Skip if we don't have anything
    if (
        array_reduce(
            $changes,
            function ($carry, $item) {
                return $carry + count($item);
            },
            0
        ) == 0
    ) {
        return;
    }

    GPLog::notice('data-update-patients-cp', $changes);

    if (isset($changes['updated'])) {
        foreach ($changes['updated'] as $i => $updated) {
            cp_patient_updated($updated);
        }
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
 * @param  array $updated The data that is updated.
 * @return null|array      The updated data or null if returned early
 *
 */
function cp_patient_updated(array $updated) : ?array
{
    GPLog::$subroutine_id = "patients-cp-updated-v2-".sha1(serialize($updated));
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

    $gpPatient = GpPatient::where('patient_id_cp', $updated['patient_id_cp'])->first();

    if (!$gpPatient) {
        GPLog::error("Could not find patient", ['update' => $updated]);
        return $updated;
    }

    $gpPatient->setGpChanges($updated);

    GPLog::debug("Readable Patient Changes", ['changes' => $gpPatient->getChangeStrings()]);

    // TODO Change this code to not call load_full_patient
    // NOTE Patient regististration will change it from 0 -> 1)
    if ($gpPatient->hasFieldChanged('patient_autofill')) {
        // Order hasn't shipped then handle
        // This updates & overwrites set_rx_messages
        // TODO Currently we are useing the add_full_fields function that
        // is called by the load_full_patient.  We need to update this
        // method to actually do the work
        $gpPatient->recalculateRxMessages();

        $log_mesage = sprintf(
            "An %s patient autofill setting has changed to %s",
            ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient',
            $updated['patient_autofill']
        );

        AuditLog::log($log_mesage, $updated);

        GPLog::notice(
            "update_patient_cp patient_autofill changed.  Confirm correct updated rx_messages",
            [
                 'patient' => $gpPatient->attributesToArray(),
                 'updated' => $updated,
                 'changed' => $gpPatient->getChangeStrings(),
                 'is_new'  => ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient'
             ]
        );
    }

    if ($gpPatient->hasFieldChanged('refills_used')) {
        GPLog::notice(
            "Patient updated in CP",
            [
                 'updated' => $updated,
                 'changed' => $gpPatient->getChangeStrings(),
                 'is_new'  => ($updated['old_pharmacy_name']) ? 'Existing Patient' : 'New Patient'
             ]
        );
    }

    // The patients secondary phone numbe has changed or bee deleted
    if ($gpPatient->hasFieldChanged('phone2')) {
        if (!$gpPatient->phone2) {
            //Phone deleted in CP so delete in WC
            AuditLog::log("Phone2 deleted for patient via CarePoint", $gpPatient->attributesToArray());
            GPLog::warning(
                "Phone2 deleted in CP",
                [
                     'updated' => $updated,
                     'patient' => $gpPatient->toArray()
                 ]
            );

            $gpPatient->updateWpMeta('billing_phone', null);
        } elseif ($gpPatient->phone2 == $gpPatient->phone1) {
            AuditLog::log("Phone2 deleted for patient via CarePoint, Copying data to WooCommerce", $updated);
            $gpPatient->deletePhoneFromCarepoint(9);
        } else {
            GPLog::notice(
                "Phone2 updated in CarePoint, Copying data to WooCommerce",
                ['patient_id_cp' => $gpPatient->patient_id_cp]
            );

            AuditLog::log("Phone2 changed for patient via CarePoint", $gpPatient->attributesToArray());

            $gpPatient->updateWpMeta('billing_phone', $gpPatient->hasFieldChanged('phone2'));
        }
    }

    //  The primary phone number for the patient has changed
    if ($gpPatient->hasFieldChanged('phone1')) {
        AuditLog::log("Phone1 changed for patient via CarePoint, Copying data to WooCommerce", $updated);
        GPLog::notice(
            "Phone1 updated in CP, Copying data to WooCommerce",
            ['patient_id_cp' => $gpPatient->patient_id_cp]
        );
    }

    // The patient status has changed
    if ($gpPatient->hasFieldChanged('patient_inactive')) {
        GPLog::notice("CP Patient Inactive Status Changed", ['updated' => $gpPatient->getChanges()]);

        AuditLog::log(
            "Patient status changed to {$gpPatient->patient_inactive} via CarePoint",
            $gpPatient->attributesToArray()
        );

        $gpPatient->updateWcActiveStatus();
    }

    if (
        $gpPatient->hasFieldChanged('payment_method_default')
        && $gpPatient->payment_method_default != PAYMENT_METHOD['AUTOPAY']
    ) {
        AuditLog::log("Autopay has been disabled via CarePoint", $gpPatient->getChanges());
        GPLog::info("Canceling 'Autopay Reminders' because patient updated payment_method_default");

        $gpPatient->cancelEvents(['Autopay Reminder']);
    }

    if ($gpPatient->hasFieldChanged('payment_method_default')
        && isset($gpPatient->payment_card_last4)) {
        AuditLog::log("Patient has updated credit card details via CarePoint", $gpPatient->getChanges());

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
            ['updated' => $gpPatient->getGpChanges()]
        );

        $gpPatient->updateEvents('Autopay Reminder', 'last4', $gpPatient->payment_card_last4);

        // Probably by generalizing the code the currently removes drugs from the refill reminders.
        // TODO Autopay Reminders (Remove Card, Card Expired, Card Changed, Order Paid Manually)
    }

    if ($gpPatient->hasAnyFieldChanged(
        [
            'first_name',
            'last_name',
            'birth_date'
        ]
    )) {
        if (isset($gpPatient->patient_id_wc)) {
            // NOTICE We intentionally no longer push these changes to woocommerce
            // wc_update_patient($patient);
            AuditLog::log(
                sprintf(
                    "Patient identifying fields have been updated in Carepoint First Name: %s,
                    Last name: %s, Birth Date: %s, Language %s.  We no longer push these changes
                    over to Woocommerce",
                    $gpPatient->first_name,
                    $gpPatient->last_name,
                    $gpPatient->birth_date,
                    $gpPatient->language
                ),
                $updated
            );
        }
    }

    GPLog::resetSubroutineId();
    return $updated;
}
