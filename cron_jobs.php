<?php

require_once 'imports/import_v2_drugs.php';
require_once 'imports/import_v2_stock_by_month.php';
require_once 'imports/import_cp_patients.php';
require_once 'imports/import_cp_rxs_single.php';
require_once 'imports/import_cp_orders.php';
require_once 'imports/import_cp_order_items.php';

require_once 'updates/update_drugs.php';
require_once 'updates/update_stock_by_month.php';
require_once 'updates/update_patients.php';
require_once 'updates/update_rxs_single.php';
require_once 'updates/update_orders.php';
require_once 'updates/update_order_items.php';

date_default_timezone_set('America/New_York');

timer("", $time);

//Imports
import_v2_drugs();
echo timer("import_v2_drugs", $time);

import_v2_stock_by_month();
echo timer("import_v2_stock", $time);

import_cp_patients();
echo timer("import_cp_patients", $time);

import_cp_rxs_single();
echo timer("import_cp_rxs_single", $time);

import_cp_orders();
echo timer("import_cp_orders", $time);

import_cp_order_items();
echo timer("import_cp_order_items", $time);

//Updates
update_drugs();
echo timer("update_drugs", $time);

update_stock_by_month();
echo timer("import_v2_stock", $time);

update_patients();
echo timer("update_patients", $time);

update_rxs_single();
echo timer("update_rxs_single", $time);

update_orders();
echo timer("update_orders", $time);

update_order_items();
echo timer("update_order_items", $time);

function timer($label, &$start) {
  $start ?: microtime(true);
  $stop  =  microtime(true);

  $diff = "
  $label: ".ceil($stop-$start)." seconds
  ";

  $start = $stop;

  return $diff;
}

//update_order_items();
