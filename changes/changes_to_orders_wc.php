<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_orders_wc($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_orders";
  $id    = "invoice_number";
  $where = "
    NOT old.patient_id_wc <=> new.patient_id_wc OR
    NOT old.payment_method <=> new.payment_method OR
    NOT old.coupon_lines <=> new.coupon_lines OR
    NOT old.order_note <=> new.order_note OR
    NOT old.order_stage_wc <=> new.order_stage_wc
  ";

  // 1st Result Set -> 1st Row -> 1st Column
  $columns = $mysql->run(get_column_names($new))[0][0]['columns'];

  //Get Deleted
  /*
  SELECT      old.*    FROM      gp_orders_wc as new    RIGHT JOIN gp_orders as old ON      old.invoice_number = new.invoice_number    WHERE      new.invoice_number IS NULL
  */
  $get_deleted_sql = get_deleted_sql($new, $old, $id);
  $deleted = $mysql->run($get_deleted_sql);

  /*
  SELECT      new.*    FROM      gp_orders_wc as new    LEFT JOIN gp_orders as old ON      old.invoice_number = new.invoice_number    WHERE      old.invoice_number IS NULL
  */
  //Get Inserted
  $get_created_sql = get_created_sql($new, $old, $id);
  $created = $mysql->run($get_created_sql);

  //Get Updated
  $get_updated_sql = get_updated_sql($new, $old, $id, $where);
  $updated = $mysql->run($get_updated_sql);

  //Since CP and not WC is our primary source, don't save Inserts or Deletes
  //because this is complicated we handle it in Update_Orders_Wc.php

  //Save Updates
  $set_updated_sql = set_updated_sql($new, $old, $id, $where);
  $mysql->run($set_updated_sql);

  log_notice('changes_to_orders_wc', [
    'get_deleted' => $get_deleted_sql,
    'get_created' => $get_created_sql,
    'get_updated' => $get_updated_sql,
    'set_updated' => $set_updated_sql
  ]);

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
