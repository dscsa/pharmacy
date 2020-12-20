<?php

require_once 'helpers/helper_full_patient.php';

use Sirum\Logging\SirumLog;

function update_patients_cp($changes) {

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
  echo $msg;
  log_info("update_patients_cp: all changes. $msg", [
    'deleted_count' => $count_deleted,
    'created_count' => $count_created,
    'updated_count' => $count_updated
  ]);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  foreach($changes['updated'] as $i => $updated) {

      SirumLog::$subroutine_id = "patients-cp-updated-".sha1(serialize($updated));

      //Overrite Rx Messages everytime a new order created otherwis same message would stay for the life of the Rx

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

      $patient = get_full_patient($updated, $mysql, true); //This updates & overwrites set_rx_messages

      log_notice("update_patient_cp patient_autofill changed.  Confirm correct updated rx_messages", [$patient, $updated, $changed, $updated['old_pharmacy_name'] ? 'Existing Patient' : 'New Patient']);
    }

    if ($updated['refills_used'] == $updated['old_refills_used'])
      log_notice("Patient updated in CP", [$updated, $changed]);

    if ( ! $updated['phone2'] AND $updated['old_phone2']) {
      //Phone deleted in CP so delete in WC
      $patient = find_patient_wc($mysql, $updated)[0];
      log_error("Phone2 deleted in CP", [$updated, $patient]);
      update_wc_phone2($mysql, $patient['patient_id_wc'], NULL);

    } else if ($updated['phone2'] AND $updated['phone2'] == $updated['phone1']) {
      //EXEC SirumWeb_AddUpdatePatHomePhone only inserts new phone numbers
      delete_cp_phone($mssql, $updated['patient_id_cp'], 9);

    } else if ($updated['phone2'] !== $updated['old_phone2']) {
      $patient = find_patient_wc($mysql, $updated)[0];
      log_notice("Phone2 updated in CP", [$updated, $patient]);
      update_wc_phone2($mysql, $patient['patient_id_wc'], $updated['phone2']);
    }

    if ($updated['phone1'] !== $updated['old_phone1']) {
      log_notice("Phone1 updated in CP. Was this handled correctly?", $updated);
    }

    if ($updated['patient_inactive'] !== $updated['old_patient_inactive']) {
      $patient = find_patient_wc($mysql, $updated)[0];
      update_wc_patient_active_status($mysql, $updated['patient_id_wc'], $updated['patient_inactive']);
      log_notice("CP Patient Inactive Status Changed", $updated);
    }

    if ($updated['payment_method_default'] != PAYMENT_METHOD['AUTOPAY'] AND $updated['old_payment_method_default'] ==  PAYMENT_METHOD['AUTOPAY'])
      cancel_events_by_person($updated['first_name'], $updated['last_name'], $updated['birth_date'], 'update_patients_wc: updated payment_method_default', ['Autopay Reminder']);

    if ($updated['payment_card_last4'] AND $updated['old_payment_card_last4'] AND $updated['payment_card_last4'] !== $updated['old_payment_card_last4']) {

      log_error("update_patients_wc: updated card_last4.  Need to replace Card Last4 in Autopay Reminder $updated[payment_method_default] $updated[old_payment_card_type] >>> $updated[payment_card_type] $updated[old_payment_card_last4] >>> $updated[payment_card_last4] $updated[payment_card_date_expired]", $updated);

      update_last4_in_autopay_reminders($updated['first_name'], $updated['last_name'], $updated['birth_date'], $updated['payment_card_last4']);
      //Probably by generalizing the code the currently removes drugs from the refill reminders.
      //TODO Autopay Reminders (Remove Card, Card Expired, Card Changed, Order Paid Manually)
    }
  }

  SirumLog::resetSubroutineId();
  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
