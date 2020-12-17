<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

//As of 2019-12-31 there are 32000 order items and this will only grow with time.
//Rather than import all of them, we only import unshipped ones.
//We want to detect deletions of "unshipped" ones (Cindy adjusting the order)
//BUT leave "shipped" ones alone (although these will appear to have been deleted since they are not imported)
function order_items_get_deleted_sql($new, $old, $id) {

  $join = join_clause($id);

  return "
    SELECT
      old.*
    FROM
      $new as new
    RIGHT JOIN $old as old ON
      $join
    WHERE
      new.$id IS NULL
    AND
      old.days_dispensed_actual IS NULL
  ";
}

//See NOTE Above
function order_items_set_deleted_sql($new, $old, $id) {

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
      old.days_dispensed_actual IS NULL
  ";
}

function changes_to_order_items($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_order_items";
  $id    = ["invoice_number", "rx_number"];

  $where = "
    NOT old.rx_dispensed_id <=> new.rx_dispensed_id OR
    NOT old.qty_dispensed_actual <=> new.qty_dispensed_actual OR
    NOT old.days_dispensed_actual <=> new.days_dispensed_actual OR
    NOT old.count_lines <=> new.count_lines OR
    NOT old.item_added_by <=> new.item_added_by
  ";

  $columns = $mysql->run(get_column_names($new))[0][0]['columns'];

  //Get Deleted - A lot of Turnover with no shipped items so let's keep historic
  $deleted = $mysql->run(order_items_get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //email('changes_to_order_items created', $created, get_created_sql($new, $old, $id), set_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  //NOTICE THIS IS A CUSTOMIZED FUNCTION!!!
  $mysql->run(order_items_set_deleted_sql($new, $old, $id));

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
