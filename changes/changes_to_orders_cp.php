<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';


function changes_to_patients_cp($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_patients";
  $id    = "patient_id_cp";
  $where = "
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    first_name
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    last_name
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    birth_date
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    email
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    phone
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    billing_phone
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    backup_pharmacy (name/npi/phone/address)
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_sulfa
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_other
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    medications_other
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_aspirin
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_penicillin
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_ampicillin
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_erythromycin
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_nsaids
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_tetracycline
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_none
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_cephalosporins
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_salicylates
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_amoxicillin
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_azithromycin
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    allergies_codeine
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    billing_address_1
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    billing_city
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    billing_postcode
        NOT old.patient_id_cp <=> new.patient_id_cp OR
    coupon / payment_method
        NOT old.patient_id_cp <=> new.patient_id_cp OR
  ";

  // 1st Result Set -> 1st Row -> 1st Column
  $columns = $mysql->run(get_column_names($new))[0][0]['columns'];

  //Get Deleted
  $deleted = $mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  //NOTICE THIS IS A CUSTOMIZED FUNCTION!!!
  $mysql->run(cp_orders_set_deleted_sql($new, $old, $id));

  //Save Inserts
  $mysql->run(set_created_sql($new, $old, $id, '('.$columns.')'));

  //Save Updates
  $mysql->run(set_updated_sql($new, $old, $id, $where));

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
