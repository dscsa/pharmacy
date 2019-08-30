<?php

require_once 'dbs/mysql_webform.php';
require_once 'helpers/helper_changes.php';

function changes_to_patients($new) {
  $mysql = new Mysql_Webform();

  $old   = "gp_patients";
  $id    = "patient_id_grx";
  $where = "
    NOT old.first_name <=> new.first_name OR
    NOT old.last_name <=> new.last_name OR
    NOT old.birth_date <=> new.birth_date OR
    NOT old.phone1 <=> new.phone1 OR
    NOT old.phone2 <=> new.phone2 OR
    NOT old.email <=> new.email OR
    NOT old.patient_autofill <=> new.patient_autofill OR
    NOT old.user_def1 <=> new.user_def1 OR
    NOT old.user_def2 <=> new.user_def2 OR
    NOT old.user_def3 <=> new.user_def3 OR
    NOT old.user_def4 <=> new.user_def4 OR
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
