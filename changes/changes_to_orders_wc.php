<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_orders_wc($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_orders";
  $id    = "invoice_number";
  $where = "
    NOT old.patient_id_wc <=> new.patient_id_wc OR
    NOT old.payment_method_actual <=> new.payment_method_actual OR
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
  log_error('changes to order wc: set_updated_sql', $set_updated_sql);
  $mysql->run($set_updated_sql);

  //SELECT post_id, meta_key, COUNT(*) as number, meta_value FROM `wp_postmeta` GROUP BY post_id, meta_key, meta_value HAVING number > 1
  /*

  DELETE t1 FROM wp_postmeta t1
  INNER JOIN wp_postmeta t2
  WHERE
    t1.meta_id < t2.meta_id AND
    t1.post_id = t2.post_id AND
    t1.meta_key = t2.meta_key AND
    t1.meta_value=t2.meta_value AND
    t1.meta_key = 'invoice_doc_id' AND
    t1.post_id = 32945


    DELETE t1 FROM wp_postmeta t1
    INNER JOIN wp_postmeta t2
    JOIN wp_posts ON t1.post_id = wp_posts.ID
    WHERE
      t1.meta_id < t2.meta_id AND
      t1.meta_key = 'invoice_number' AND
      t1.meta_value = t2.meta_value AND
      (wp_posts.post_status LIKE 'wc-prepare-%' OR wp_posts.post_status LIKE 'wc-confirm-%')


    DELETE wp_posts
    FROM wp_posts
    LEFT JOIN wp_postmeta ON post_id = ID AND meta_key = 'invoice_number'
    WHERE
      post_type = 'shop_order' AND
      meta_id IS NULL AND
      post_status LIKE 'wc-confirm-%'


    SELECT * 
    FROM wp_posts
    LEFT JOIN wp_postmeta ON post_id = ID AND meta_key = 'invoice_number'
    WHERE post_type = 'shop_order'
    AND post_status != 'trash'
    AND meta_id IS NULL
    ORDER BY ID ASC

    SELECT meta_key, meta_value, GROUP_CONCAT(post_status), COUNT(*) as number
    FROM `wp_postmeta`
    JOIN wp_posts ON post_id = wp_posts.ID
    WHERE meta_key
    GROUP BY meta_value
    HAVING number > 1

    1XqG_DR6RuCOrD-zVUqh0RDOBUIq9tmzLWzI7iywhHQM

  */

  log_info('changes_to_orders_wc', [
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
