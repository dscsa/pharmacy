<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_orders($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_orders";
  $id    = "invoice_number";
  $where = "
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    NOT old.item_count <=> new.item_count OR
    NOT old.order_source <=> new.order_source OR
    NOT old.order_stage <=> new.order_stage OR
    NOT old.order_status <=> new.order_status OR
    -- Not in CP -- NOT old.invoice_doc_id <=> new.invoice_doc_id OR
    NOT old.order_address1 <=> new.order_address1 OR
    NOT old.order_address2 <=> new.order_address2 OR
    NOT old.order_city <=> new.order_city OR
    NOT old.order_state <=> new.order_state OR
    NOT old.order_zip <=> new.order_zip OR
    NOT old.tracking_number <=> new.tracking_number OR
    NOT old.order_date_added <=> new.order_date_added OR
    NOT old.order_date_dispensed <=> new.order_date_dispensed OR
    NOT old.order_date_shipped <=> new.order_date_shipped
    -- False Positives -- NOT old.order_date_changed <=> new.order_date_changed
    -- Not in CP -- NOT old.order_date_returned <=> new.order_date_returned
  ";

  //Get Deleted
  $deleted = $mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where), true);

  mail('adam@sirum.org', "CRON: changes_to_orders", print_r([$updated, $created, $deleted] , true));

  //Save Deletes
  $mysql->run(set_deleted_sql($new, $old, $id));

  //Save Inserts
  $mysql->run(set_created_sql($new, $old, $id));

  //Save Updates
  //$mysql->run(set_updated_sql($new, $old, $id, $where));

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
