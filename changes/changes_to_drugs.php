<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_drugs($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_drugs";
  $id    = "drug_name";
  $where = "
    NOT old.drug_gsns <=> new.drug_gsns
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
