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
    NOT old.order_stage_wc <=> new.order_stage_wc
  ";

  $columns = $mysql->run(get_column_names($table))[0][0];

  //Get Deleted
  $deleted = $mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  $mysql->run(set_deleted_sql($new, $old, $id));

  //Save Inserts
  $mysql->run(set_created_sql($new, $old, $id, '('.$columns.')'));

  //Save Updates
  $mysql->run(set_updated_sql($new, $old, $id, $where));

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
