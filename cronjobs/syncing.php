<?php

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'helpers/helper_log.php';
require_once 'helpers/helper_constants.php';

require_once 'imports/import_v2_drugs.php';
require_once 'imports/import_v2_stock_by_month.php';
require_once 'imports/import_cp_rxs_single.php';
require_once 'imports/import_cp_patients.php';
require_once 'imports/import_cp_orders.php';
require_once 'imports/import_cp_order_items.php';

require_once 'updates/update_drugs.php';
require_once 'updates/update_stock_by_month.php';
require_once 'updates/update_rxs_single.php';
require_once 'updates/update_patients.php';
require_once 'updates/update_orders.php';
require_once 'updates/update_order_items.php';

timer("", $time);

//Imports
import_v2_drugs();
log_info(timer("import_v2_drugs", $time));

import_v2_stock_by_month();
log_info(timer("import_v2_stock_by_month", $time));

import_cp_rxs_single();
log_info(timer("import_cp_rxs_single", $time));

import_cp_patients();
log_info(timer("import_cp_patients", $time));

import_cp_order_items();
log_info(timer("import_cp_order_items", $time));

import_cp_orders();
log_info(timer("import_cp_orders", $time));

//Updates
update_drugs();
log_info(timer("update_drugs", $time));

update_stock_by_month();
log_info(timer("update_stock_by_month", $time));

update_rxs_single();
log_info(timer("update_rxs_single", $time));

update_patients();
log_info(timer("update_patients", $time));

update_order_items();
log_info(timer("update_order_items", $time));

update_orders();
log_info(timer("update_orders", $time));

log_info("

---- DONE!!! ----

");

 //This does the final emaul
mail('adam@sirum.org', "WebForm CRON Finished", log_info());


function timer($label, &$start) {
  $start = $start ?: [microtime(true), microtime(true)];
  $stop  = microtime(true);

  $diff = "
  $label: ".ceil($stop-$start[0])." seconds of ".ceil($stop-$start[1])." total
  ";

  $start[0] = $stop;

  return $diff;
}

//update_order_items();
