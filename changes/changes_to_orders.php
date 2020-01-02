<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

//Returned Orders like 24174 have status_cn == 3 and liCount == 0.  This makes them appear to be deleted
//We don't want to update them with this information BUT we do want to keep the old order in the database and add a date_returned
function orders_set_deleted_sql($new, $old, $id) {

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
      old.tracking_number IS NULL
  ";
}

function changes_to_orders($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_orders";
  $id    = "invoice_number";
  $where = "
    NOT old.patient_id_cp <=> new.patient_id_cp OR
    NOT old.count_items <=> new.count_items OR
    NOT old.order_source <=> new.order_source OR
    NOT old.order_stage <=> new.order_stage OR
    -- NOT old.order_status <=> new.order_status OR -- SEEMS DUPLICATIVE WITH STAGE AND CAUSES CHANGES ON Rx Expired >>> Entered
    NOT old.order_address1 <=> new.order_address1 OR
    NOT old.order_address2 <=> new.order_address2 OR
    NOT old.order_city <=> new.order_city OR
    NOT old.order_state <=> new.order_state OR
    NOT old.order_zip <=> new.order_zip OR
    NOT old.tracking_number <=> new.tracking_number OR
    NOT old.order_date_added <=> new.order_date_added OR
    NOT old.order_date_dispensed <=> new.order_date_dispensed OR
    NOT old.order_date_shipped <=> new.order_date_shipped
    -- Not in CP -- NOT old.invoice_doc_id <=> new.invoice_doc_id OR
    -- False Positives -- NOT old.order_date_changed <=> new.order_date_changed OR
    -- Not in CP -- NOT old.order_date_returned <=> new.order_date_returned
  ";

  //Get Deleted
  $deleted = $mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  //NOTICE THIS IS A CUSTOMIZED FUNCTION!!!
  $mysql->run(orders_set_deleted_sql($new, $old, $id));

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
