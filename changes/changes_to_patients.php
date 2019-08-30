<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_patients($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_patients";
  $id    = "patient_id_cp";
  $where = "
    NOT old.first_name <=> new.first_name OR
    NOT old.last_name <=> new.last_name OR
    NOT old.birth_date <=> new.birth_date OR
    NOT old.phone1 <=> new.phone1 OR
    NOT old.phone2 <=> new.phone2 OR
    NOT old.email <=> new.email OR
    NOT old.patient_autofill <=> new.patient_autofill OR
    NOT old.pharmacy_name <=> new.pharmacy_name OR
    NOT old.pharmacy_npi <=> new.pharmacy_npi OR
    NOT old.pharmacy_fax <=> new.pharmacy_fax OR
    NOT old.pharmacy_phone <=> new.pharmacy_phone OR
    NOT old.pharmacy_address <=> new.pharmacy_address OR
    NOT old.card_type <=> new.card_type OR
    NOT old.card_last4 <=> new.card_last4 OR
    NOT old.card_date_expired <=> new.card_date_expired OR
    NOT old.billing_method <=> new.billing_method OR
    NOT old.billing_coupon <=> new.billing_coupon OR
    NOT old.patient_address1 <=> new.patient_address1 OR
    NOT old.patient_address2 <=> new.patient_address2 OR
    NOT old.patient_city <=> new.patient_city OR
    NOT old.patient_state <=> new.patient_state OR
    NOT old.patient_zip <=> new.patient_zip OR
    NOT old.total_fills <=> new.total_fills OR
    NOT old.patient_status <=> new.patient_status OR
    NOT old.lang <=> new.lang OR
    NOT old.patient_date_added <=> new.patient_date_added
    -- False Positives -- NOT old.patient_date_changed <=> new.patient_date_changed
  ";

  //Get Deleted
  $deleted = $mysql->run(get_deleted_sql($new, $old, $id), true);

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id), true);

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where), true);

  //Save Deletes
  $mysql->run(set_deleted_sql($new, $old, $id), true);

  //Save Inserts
  $mysql->run(set_created_sql($new, $old, $id), true);

  //Save Updates
  $mysql->run(set_updated_sql($new, $old, $id, $where), true);

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
