<?php

require_once 'changes/changes_to_patients_cp.php';

function update_patients_cp() {

  $changes = changes_to_patients_cp("gp_patients_cp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_patients_cp: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  foreach($changes['updated'] as $i => $updated) {

    $changed = changed_fields($updated);

    if ($updated['patient_autofill'] != $updated['old_patient_autofill']) {

      $profile = get_full_order($updated, $mysql, false); //This updates & overwrites set_rx_messages

      log_error("update_patient_cp patient_autofill changed.  update rx_messages?", [$profile, $updated, $changed]);
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
      log_error("Phone2 updated in CP", [$updated, $patient]);
      update_wc_phone2($mysql, $patient['patient_id_wc'], $updated['phone2']);
    }

    if ($updated['phone1'] !== $updated['old_phone1']) {
      log_error("Phone1 updated in CP. Was this handled correctly?", $updated);
    }

  }
  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
