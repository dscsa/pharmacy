<?php

require_once 'dbs/mysql_webform.php';

function changes_to_orders() {
  $mysql = new Mysql_Webform();

  //Get Upserts (Updates & Inserts)
  $upserts = $mysql->run("
    SELECT new.*
    FROM
      gp_orders_grx as new
    LEFT JOIN gp_orders as old ON
      old.invoice_number <=> new.invoice_number,
      old.patient_id_grx <=> new.patient_id_grx,
      old.order_source <=> new.order_source,
      old.order_stage <=> new.order_stage,
      old.order_status <=> new.order_status,
      old.invoice_doc_id <=> new.invoice_doc_id,
      old.order_address1 <=> new.order_address1,
      old.order_address2 <=> new.order_address2,
      old.order_city <=> new.order_city,
      old.order_state <=> new.order_state,
      old.order_zip <=> new.order_zip,
      old.tracking_number <=> new.tracking_number,
      -- old.order_date_added <=> new.order_date_added,
      old.order_date_dispensed <=> new.order_date_dispensed,
      old.order_date_shipped <=> new.order_date_shipped,
      -- old.order_date_changed <=> new.order_date_changed
    WHERE
      old.invoice_number IS NULL
  ");

  //Get Removals
  $removals = $mysql->run("
    SELECT old.*
    FROM
      gp_orders_grx as new
    RIGHT JOIN gp_orders as old ON
      old.invoice_number <=> new.invoice_number,
      old.patient_id_grx <=> new.patient_id_grx,
      old.order_source <=> new.order_source,
      old.order_stage <=> new.order_stage,
      old.order_status <=> new.order_status,
      old.invoice_doc_id <=> new.invoice_doc_id,
      old.order_address1 <=> new.order_address1,
      old.order_address2 <=> new.order_address2,
      old.order_city <=> new.order_city,
      old.order_state <=> new.order_state,
      old.order_zip <=> new.order_zip,
      old.tracking_number <=> new.tracking_number,
      -- old.order_date_added <=> new.order_date_added,
      old.order_date_dispensed <=> new.order_date_dispensed,
      old.order_date_shipped <=> new.order_date_shipped,
      -- old.order_date_changed <=> new.order_date_changed
    WHERE
      new.invoice_number IS NULL
  ");

  //Do Upserts
  $mysql->run("
    INSERT INTO gp_orders
    SELECT new.*
    FROM gp_orders_grx as new
    LEFT JOIN gp_orders as old ON
      old.invoice_number <=> new.invoice_number AND
      old.patient_id_grx <=> new.patient_id_grx AND
      old.order_source <=> new.order_source AND
      old.order_stage <=> new.order_stage AND
      old.order_status <=> new.order_status AND
      old.invoice_doc_id <=> new.invoice_doc_id AND
      old.order_address1 <=> new.order_address1 AND
      old.order_address2 <=> new.order_address2 AND
      old.order_city <=> new.order_city AND
      old.order_state <=> new.order_state AND
      old.order_zip <=> new.order_zip AND
      old.tracking_number <=> new.tracking_number AND
      -- old.order_date_added <=> new.order_date_added AND
      old.order_date_dispensed <=> new.order_date_dispensed AND
      old.order_date_shipped <=> new.order_date_shipped
      -- old.order_date_changed <=> new.order_date_changed
    WHERE
      old.invoice_number IS NULL
    ON DUPLICATE KEY UPDATE
      invoice_number = new.invoice_number,
      patient_id_grx = new.patient_id_grx,
      order_source = new.order_source,
      order_stage = new.order_stage,
      order_status = new.order_status,
      invoice_doc_id = new.invoice_doc_id,
      order_address1 = new.order_address1,
      order_address2 = new.order_address2,
      order_city = new.order_city,
      order_state = new.order_state,
      order_zip = new.order_zip,
      tracking_number = new.tracking_number,
      -- order_date_added = new.order_date_added,
      order_date_dispensed = new.order_date_dispensed,
      order_date_shipped = new.order_date_shipped,
      -- order_date_changed = new.order_date_changed
  ");

  //Do Removals
  $mysql->run("
    DELETE old
    FROM gp_orders_grx as new
    RIGHT JOIN gp_orders as old ON
      old.invoice_number <=> new.invoice_number,
      old.patient_id_grx <=> new.patient_id_grx,
      old.order_source <=> new.order_source,
      old.order_stage <=> new.order_stage,
      old.order_status <=> new.order_status,
      old.invoice_doc_id <=> new.invoice_doc_id,
      old.order_address1 <=> new.order_address1,
      old.order_address2 <=> new.order_address2,
      old.order_city <=> new.order_city,
      old.order_state <=> new.order_state,
      old.order_zip <=> new.order_zip,
      old.tracking_number <=> new.tracking_number,
      -- old.order_date_added <=> new.order_date_added,
      old.order_date_dispensed <=> new.order_date_dispensed,
      old.order_date_shipped <=> new.order_date_shipped,
      -- old.order_date_changed <=> new.order_date_changed
    WHERE
      new.invoice_number IS NULL
  ");

  return ['upserts' => $upserts, 'removals' => $removals];
}
