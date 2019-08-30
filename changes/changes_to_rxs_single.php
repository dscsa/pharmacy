<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_rxs_single($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_rxs_single";
  $id    = "rx_number";
  $where = "
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    NOT old.drug_name <=> new.drug_name OR
    NOT old.drug_name_raw <=> new.drug_name_raw OR
    NOT old.gcn <=> new.gcn OR
    NOT old.refills_left <=> new.refills_left OR
    NOT old.refills_original <=> new.refills_original OR
    NOT old.qty_written <=> new.qty_written OR
    NOT old.sig_raw <=> new.sig_raw OR
      -- Not in GRX -- NOT old.sig_clean <=> new.sig_clean OR
      -- Not in GRX -- NOT old.sig_qty_per_day <=> new.sig_qty_per_day OR
      -- Not in GRX -- NOT old.sig_qty_per_time <=> new.sig_qty_per_time OR
      -- Not in GRX -- NOT old.sig_frequency <=> new.sig_frequency OR
      -- Not in GRX -- NOT old.sig_frequency_numerator <=> new.sig_frequency_numerator OR
      -- Not in GRX -- NOT old.sig_frequency_denominator <=> new.sig_frequency_denominator OR
    NOT old.rx_autofill <=> new.rx_autofill OR
    NOT old.refill_date_first <=> new.refill_date_first OR
    NOT old.refill_date_last <=> new.refill_date_last OR
    NOT old.refill_date_manual <=> new.refill_date_manual OR
    NOT old.refill_date_default <=> new.refill_date_default OR
    NOT old.rx_status <=> new.rx_status OR
    NOT old.rx_stage <=> new.rx_stage OR
    NOT old.rx_source <=> new.rx_source OR
    NOT old.rx_transfer <=> new.rx_transfer OR
    NOT old.provider_npi <=> new.provider_npi OR
    NOT old.provider_first_name <=> new.provider_first_name OR
    NOT old.provider_last_name <=> new.provider_last_name OR
    NOT old.provider_phone <=> new.provider_phone OR
    NOT old.rx_date_changed <=> new.rx_date_changed OR
    NOT old.rx_date_expired <=> new.rx_date_expired
  ";

  //Get Deleted
  $deleted = $mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  //Save Deletes
  $mysql->run(set_deleted_sql($new, $old, $id));

  //Save Inserts
  $mysql->run(set_created_sql($new, $old, $id));

  //Save Updates
  $mysql->run(set_updated_sql($new, $old, $id, $where));

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
