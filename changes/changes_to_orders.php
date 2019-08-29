<?php

require_once 'dbs/mysql_webform.php';

function changes_to_orders() {
  $mysql = new Mysql_Webform();

  $where_changes = "
    NOT old.patient_id_grx <=> new.patient_id_grx OR
    NOT old.order_source <=> new.order_source OR
    NOT old.order_stage <=> new.order_stage OR
    NOT old.order_status <=> new.order_status OR
    -- Not in GRX NOT old.invoice_doc_id <=> new.invoice_doc_id OR
    NOT old.order_address1 <=> new.order_address1 OR
    NOT old.order_address2 <=> new.order_address2 OR
    NOT old.order_city <=> new.order_city OR
    NOT old.order_state <=> new.order_state OR
    NOT old.order_zip <=> new.order_zip OR
    NOT old.tracking_number <=> new.tracking_number OR
    NOT old.order_date_added <=> new.order_date_added OR
    NOT old.order_date_dispensed <=> new.order_date_dispensed OR
    NOT old.order_date_shipped <=> new.order_date_shipped OR
    NOT old.order_date_changed <=> new.order_date_changed
  ";

  //Get Removals
  $deleted = $mysql->run("
    SELECT
      new.*
    FROM
      gp_orders_grx as new
    RIGHT JOIN gp_orders as old ON
      old.invoice_number = new.invoice_number
    WHERE
      new.invoice_number IS NULL
  ", true);

  //Get Inserts
  $created = $mysql->run("
    SELECT
      new.*
    FROM
      gp_orders_grx as new
    LEFT JOIN gp_orders as old ON
      old.invoice_number = new.invoice_number
    WHERE
      old.invoice_number IS NULL
  ", true);

  //Get Updates
  $updated = $mysql->run("
    SELECT
      new.*,
      old.invoice_number as old_invoice_number,
      old.patient_id_grx as old_patient_id_grx,
      old.order_source as old_order_source,
      old.order_stage as old_order_stage,
      old.order_status as old_order_status,
      old.invoice_doc_id as old_invoice_doc_id,
      old.order_address1 as old_order_address1,
      old.order_address2 as old_order_address2,
      old.order_city as old_order_city,
      old.order_state as old_order_state,
      old.order_zip as old_order_zip,
      old.tracking_number as old_tracking_number,
      old.order_date_added as old_order_date_added,
      old.order_date_dispensed as old_order_date_dispensed,
      old.order_date_shipped as old_order_date_shipped,
      old.order_date_changed as old_order_date_changed
    FROM
      gp_orders_grx as new
    JOIN gp_orders as old ON
      old.invoice_number = new.invoice_number
    WHERE $where_changes
  ", true);

  //Do Removals
  $mysql->run("
    DELETE
      old
    FROM
      gp_orders_grx as new
    RIGHT JOIN gp_orders as old ON
      old.invoice_number = new.invoice_number
    WHERE
      new.invoice_number IS NULL
  ", true);

  //Do Inserts
  $mysql->run("
    INSERT INTO gp_orders
    SELECT new.*
    FROM
      gp_orders_grx as new
    LEFT JOIN gp_orders as old ON
      old.invoice_number = new.invoice_number
    WHERE
      old.invoice_number IS NULL
  ", true);

  //Do Updates
  $mysql->run("
    UPDATE gp_orders as old
    LEFT JOIN gp_orders_grx as new ON
      old.invoice_number = new.invoice_number
    SET
      old.invoice_number = new.invoice_number,
      old.patient_id_grx = new.patient_id_grx,
      old.order_source = new.order_source,
      old.order_stage = new.order_stage,
      old.order_status = new.order_status,
      -- Not in GRX NOT old.invoice_doc_id = new.invoice_doc_id,
      old.order_address1 = new.order_address1,
      old.order_address2 = new.order_address2,
      old.order_city = new.order_city,
      old.order_state = new.order_state,
      old.order_zip = new.order_zip,
      old.tracking_number = new.tracking_number,
      old.order_date_added = new.order_date_added,
      old.order_date_dispensed = new.order_date_dispensed,
      old.order_date_shipped = new.order_date_shipped,
      old.order_date_changed = new.order_date_changed
    WHERE $where_changes
  ", true);

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
