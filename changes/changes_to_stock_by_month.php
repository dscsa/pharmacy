<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_changes.php';

function changes_to_stock_by_month($new) {
  $mysql = new Mysql_Wc();

  $old   = "gp_stock_by_month";
  $id    = ["drug_generic", "month"];
  $where = "
    NOT old.inventory_sum <=> new.inventory_sum OR
    NOT old.inventory_count <=> new.inventory_count OR
    NOT old.inventory_min <=> new.inventory_min OR
    NOT old.inventory_max <=> new.inventory_max OR
    NOT old.inventory_sumsqr <=> new.inventory_sumsqr OR
    NOT old.verified_sum <=> new.verified_sum OR
    NOT old.verified_count <=> new.verified_count OR
    NOT old.verified_min <=> new.verified_min OR
    NOT old.verified_max <=> new.verified_max OR
    NOT old.verified_sumsqr <=> new.verified_sumsqr OR
    NOT old.refused_sum <=> new.refused_sum OR
    NOT old.refused_count <=> new.refused_count OR
    NOT old.refused_min <=> new.refused_min OR
    NOT old.refused_max <=> new.refused_max OR
    NOT old.refused_sumsqr <=> new.refused_sumsqr OR
    NOT old.expired_sum <=> new.expired_sum OR
    NOT old.expired_count <=> new.expired_count OR
    NOT old.expired_min <=> new.expired_min OR
    NOT old.expired_max <=> new.expired_max OR
    NOT old.expired_sumsqr <=> new.expired_sumsqr OR
    NOT old.disposed_sum <=> new.disposed_sum OR
    NOT old.disposed_count <=> new.disposed_count OR
    NOT old.disposed_min <=> new.disposed_min OR
    NOT old.disposed_max <=> new.disposed_max OR
    NOT old.disposed_sumsqr <=> new.disposed_sumsqr OR
    NOT old.dispensed_sum <=> new.dispensed_sum OR
    NOT old.dispensed_count <=> new.dispensed_count OR
    NOT old.dispensed_min <=> new.dispensed_min OR
    NOT old.dispensed_max <=> new.dispensed_max OR
    NOT old.dispensed_sumsqr <=> new.dispensed_sumsqr
  ";

  //Get Deleted - A lot of Turnover with a 3 month window so let's keep historic
  $deleted = [[]]; //$mysql->run(get_deleted_sql($new, $old, $id));

  //Get Inserted
  $created = $mysql->run(get_created_sql($new, $old, $id));

  //Get Updated
  $updated = $mysql->run(get_updated_sql($new, $old, $id, $where));
  //email('changes_to_stock_by_month: get updated', get_updated_sql($new, $old, $id, $where), $updated);

  //Save Deletes - A lot of Turnover with a 3 month window so let's keep historic
  //$mysql->run(set_deleted_sql($new, $old, $id));

  //Save Inserts
  $mysql->run(set_created_sql($new, $old, $id));
  //email('changes_to_stock_by_month: set updated', set_created_sql($new, $old, $id));

  //Save Updates
  $mysql->run(set_updated_sql($new, $old, $id, $where));

  return [
    'deleted' => $deleted[0],
    'created' => $created[0],
    'updated' => $updated[0]
  ];
}
