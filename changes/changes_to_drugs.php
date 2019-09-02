<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_drugs($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_drugs";
  $id    = "drug_generic";
  $where = "
    NOT old.drug_brand <=> new.drug_brand OR
    NOT old.drug_gsns <=> new.drug_gsns OR
    NOT old.drug_ordered <=> new.drug_ordered OR
    NOT old.price30 <=> new.price30 OR
    NOT old.price90 <=> new.price90 OR
    NOT old.qty_repack <=> new.qty_repack OR
    NOT old.qty_min <=> new.min_qty OR
    NOT old.days_min <=> new.days_min OR
    NOT old.max_inventory <=> new.max_inventory OR
    NOT old.message_display <=> new.message_display OR
    NOT old.message_verified <=> new.message_verified OR
    NOT old.message_destroyed <=> new.message_destroyed OR
    NOT old.price_goodrx <=> new.price_goodrx OR
    NOT old.price_nadac <=> new.price_nadac OR
    NOT old.price_retail <=> new.price_retail OR
    NOT old.count_ndcs <=> new.count_ndcs
  ";

  //Get Deleted
  $deleted = $mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));

  //Save Deletes
  $mysql->run(set_deleted_sql($new, $old, $id));

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
