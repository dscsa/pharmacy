<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_order_items($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_order_items";
  $id    = ["invoice_number", "rx_number"];

  $where = "
    NOT old.qty_dispensed_actual <=> new.qty_dispensed_actual OR
    NOT old.days_dispensed_actual <=> new.days_dispensed_actual OR
    NOT old.item_added_by <=> new.item_added_by
  ";

  //Get Deleted - A lot of Turnover with no shipped items so let's keep historic
  $deleted = [[]]; //$mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  //Save Deletes - A lot of Turnover with no shipped items so let's keep historic
  //$mysql->run(set_deleted_sql($new, $old, $id));

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
