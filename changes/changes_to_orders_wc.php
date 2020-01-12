<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

//Returned Orders like 24174 have status_cn == 3 and liCount == 0.  This makes them appear to be deleted
//We don't want to update them with this information BUT we do want to keep the old order in the database and add a date_returned
function wc_orders_set_deleted_sql($new, $old, $id) {

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
      old.order_stage_wc = 'processing' OR
      old.order_stage_wc = 'trash'
  ";
}


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
  $deleted_sql = get_deleted_sql($new, $old, $id);
  $deleted = $mysql->run($deleted_sql);

  //Get Inserted
  $created_sql = get_created_sql($new, $old, $id);
  $created = $mysql->run($created_sql);

  //Get Updated
  $updated_sql = get_updated_sql($new, $old, $id, $where);
  $updated = $mysql->run($updated_sql);

  log_notice('changes_to_orders_wc', get_defined_vars());

  //Custom function to not remove to many orders until things settle
  //$mysql->run(wc_orders_set_deleted_sql($new, $old, $id));

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
