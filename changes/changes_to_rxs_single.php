<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function rxs_single_set_deleted_sql($new, $old, $id) {

  $join = join_clause($id);

  return "
    DELETE
      old
    FROM
      $new as new
    RIGHT JOIN $old as old ON
      $join
    WHERE
      new.$id IS NULL
    AND
      old.rx_date_changed > @today - ".DAYS_OF_RXS_TO_IMPORT;
}

function changes_to_rxs_single($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_rxs_single";
  $id    = "rx_number";
  $where = "
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    NOT old.drug_name <=> new.drug_name OR
    NOT old.rx_gsn <=> new.rx_gsn OR
    NOT old.days_left <=> new.days_left OR
    NOT old.refills_left <=> new.refills_left OR
    NOT old.refills_original <=> new.refills_original OR
    NOT old.qty_left <=> new.qty_left OR
    NOT old.qty_original <=> new.qty_original OR
    NOT old.sig_actual <=> new.sig_actual OR
      -- Not in CP -- NOT old.sig_clean <=> new.sig_clean OR
      -- Not in CP -- NOT old.sig_qty_per_day_deafult <=> new.sig_qty_per_day_default OR
      -- Not in CP -- NOT old.sig_qty_per_time <=> new.sig_qty_per_time OR
      -- Not in CP -- NOT old.sig_frequency <=> new.sig_frequency OR
      -- Not in CP -- NOT old.sig_frequency_numerator <=> new.sig_frequency_numerator OR
      -- Not in CP -- NOT old.sig_frequency_denominator <=> new.sig_frequency_denominator OR
    NOT old.rx_autofill <=> new.rx_autofill OR
    NOT old.refill_date_first <=> new.refill_date_first OR
    NOT old.refill_date_last <=> new.refill_date_last OR
    NOT old.refill_date_manual <=> new.refill_date_manual OR
    NOT old.refill_date_default <=> new.refill_date_default OR
    NOT old.rx_status <=> new.rx_status OR
    NOT old.rx_stage <=> new.rx_stage OR
    NOT old.rx_source <=> new.rx_source OR
    NOT old.rx_transfer <=> new.rx_transfer OR
      -- NOT old.rx_date_transferred <=> new.rx_date_transferred OR
    NOT old.provider_npi <=> new.provider_npi OR
    NOT old.provider_first_name <=> new.provider_first_name OR
    NOT old.provider_last_name <=> new.provider_last_name OR
    NOT old.provider_clinic <=> new.provider_clinic OR
    NOT old.provider_phone <=> new.provider_phone OR
    NOT old.rx_date_changed <=> new.rx_date_changed OR
    NOT old.rx_date_expired <=> new.rx_date_expired
  ";

  // 1st Result Set -> 1st Row -> 1st Column
  $columns = $mysql->run(get_column_names($new))[0][0]['columns'];

  //Get Deleted
  //$deleted = $mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  //Save Deletes
  //We are only importing recent rows to speed up the import process, so don't delete rows just because they are older.  However, still need to delete (recent?) rows that are voided!
  $mysql->run(rxs_single_set_deleted_sql($new, $old, $id));

  //Save Inserts
  $mysql->run(set_created_sql($new, $old, $id, '('.$columns.')'));

  //Save Updates
  $mysql->run(set_updated_sql($new, $old, $id, $where));

  return [
    'deleted' => [], //$deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
