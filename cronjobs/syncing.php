<?php

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'keys.php';
require_once 'helpers/helper_log.php';
require_once 'helpers/helper_constants.php';

require_once 'imports/import_v2_drugs.php';
require_once 'imports/import_v2_stock_by_month.php';
require_once 'imports/import_cp_rxs_single.php';
require_once 'imports/import_wc_patients.php';
require_once 'imports/import_cp_patients.php';
require_once 'imports/import_cp_order_items.php';
require_once 'imports/import_wc_orders.php';
require_once 'imports/import_cp_orders.php';

require_once 'updates/update_drugs.php';
require_once 'updates/update_stock_by_month.php';
require_once 'updates/update_rxs_single.php';
require_once 'updates/update_patients_wc.php';
require_once 'updates/update_patients_cp.php';
require_once 'updates/update_order_items.php';
require_once 'updates/update_orders_wc.php';
require_once 'updates/update_orders_cp.php';

timer("", $time);
$email = '';

//Imports

import_v2_drugs();
$email .= timer("import_v2_drugs", $time);

import_v2_stock_by_month();
$email .= timer("import_v2_stock_by_month", $time);

import_cp_rxs_single();
$email .= timer("import_cp_rxs_single", $time);

import_wc_patients();
$email .= timer("import_wc_patients", $time);

import_cp_patients();
$email .= timer("import_cp_patients", $time);

import_cp_order_items();
$email .= timer("import_cp_order_items", $time);

import_wc_orders();
$email .= timer("import_wc_orders", $time);

import_cp_orders();
$email .= timer("import_cp_orders", $time);

//Updates

update_drugs();
$email .= timer("update_drugs", $time);

update_stock_by_month();
$email .= timer("update_stock_by_month", $time);

update_rxs_single();
$email .= timer("update_rxs_single", $time);

update_patients_wc();
$email .= timer("update_patients_wc", $time);

update_patients_cp();
$email .= timer("update_patients_cp", $time);

update_order_items();
$email .= timer("update_order_items", $time);

update_orders_wc();
$email .= timer("update_orders_wc", $time);

update_orders_cp();
$email .= timer("update_orders_cp", $time);

watch_invoices();
$email .= timer("watch_invoices", $time);

if ($email) {
  log_info("WebForm CRON Finished", get_defined_vars());
}
