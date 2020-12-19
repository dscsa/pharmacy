<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_patients_wc($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_patients";
  $id    = "patient_id_wc";
  $where = "
    NOT old.patient_id_wc <=> new.patient_id_wc OR
    NOT old.first_name <=> new.first_name OR
    NOT REPLACE(old.last_name, '*', '') <=> new.last_name OR
    NOT old.birth_date <=> new.birth_date OR
    NOT old.patient_date_registered <=> new.patient_date_registered OR
    NOT old.medications_other <=> new.medications_other OR
    NOT old.phone1 <=> new.phone1 OR
    NOT old.phone2 <=> new.phone2 OR -- Right now often Phone2 === Phone1, don't trigger change in this case
    NOT old.email <=> new.email OR
    -- NOT old.patient_autofill <=> new.patient_autofill OR -- not saved in WooCommerce, Guardian Only
    NOT old.pharmacy_name <=> new.pharmacy_name OR
    NOT old.pharmacy_name <=> new.pharmacy_name OR
    NOT old.pharmacy_npi <=> new.pharmacy_npi OR
    NOT old.pharmacy_fax <=> new.pharmacy_fax OR
    NOT old.pharmacy_phone <=> new.pharmacy_phone OR
    -- NOT old.pharmacy_address <=> new.pharmacy_address OR -- We only save a partial address in CP so will always differ
    NOT old.payment_method_default <=> new.payment_method_default OR
    -- NOT old.payment_card_type <=> new.payment_card_type OR -- not saved in WooCommerce, Guardian Only
    -- NOT old.payment_card_last4 <=> new.payment_card_last4 OR -- not saved in WooCommerce, Guardian Only
    -- NOT old.payment_card_date_expired <=> new.payment_card_date_expired OR -- not saved in WooCommerce, Guardian Only
    NOT old.payment_coupon <=> new.payment_coupon OR
    NOT old.tracking_coupon <=> new.tracking_coupon OR
    NOT old.patient_address1 <=> new.patient_address1 OR
    NOT old.patient_address2 <=> new.patient_address2 OR
    NOT old.patient_city <=> new.patient_city OR
    NOT old.patient_state <=> new.patient_state OR
    NOT old.patient_zip <=> new.patient_zip OR
    NOT old.language <=> new.language OR

    NOT old.allergies_none <=> new.allergies_none OR
    NOT old.allergies_tetracycline <=> new.allergies_tetracycline OR
    NOT old.allergies_cephalosporins <=> new.allergies_cephalosporins OR
    NOT old.allergies_sulfa <=> new.allergies_sulfa OR
    NOT old.allergies_aspirin <=> new.allergies_aspirin OR
    NOT old.allergies_penicillin <=> new.allergies_penicillin OR
    NOT old.allergies_erythromycin <=> new.allergies_erythromycin OR
    NOT old.allergies_codeine <=> new.allergies_codeine OR
    NOT old.allergies_nsaids <=> new.allergies_nsaids OR
    NOT old.allergies_salicylates <=> new.allergies_salicylates OR
    NOT old.allergies_azithromycin <=> new.allergies_azithromycin OR
    NOT old.allergies_amoxicillin <=> new.allergies_amoxicillin OR
    NOT old.allergies_other <=> new.allergies_other
  ";

  //Get Deleted
  $sql = get_deleted_sql($new, $old, $id);
  echo "\n$sql\n";
  $deleted = $mysql->run($sql);

  //Get Inserted
  $sql = get_created_sql($new, $old, $id);
  //log_error('changes_to_patients_wc: created', $sql);
  $created = $mysql->run($sql);

  //Get Updated
  $sql = get_updated_sql($new, $old, $id, $where);
  //log_error('changes_to_patients_wc: updated', $sql);
  $updated = $mysql->run($sql);

  //Save Deletes
  $mysql->run(set_deleted_sql($new, $old, $id));

  //Save Inserts
  //$mysql->run(set_created_sql($new, $old, $id));

  //Save Updates
  //$mysql->run(set_updated_sql($new, $old, $id, $where));

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
