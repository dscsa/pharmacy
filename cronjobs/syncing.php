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

//Don't run next cron job if previous one is still running!!! Webform Log Notices 2020-02-29 04:47pm
//https://stackoverflow.com/questions/10552016/how-to-prevent-the-cron-job-execution-if-it-is-already-running
$f = fopen('readme.md', 'w') or log_error('Webform Cron Job Cannot Create Lock File');

if ( ! flock($f, LOCK_EX | LOCK_NB))
  return log_error('Skipping Webform Cron Job Because Previous One Is Still Running');

timer("", $time);
$email = '';

//Imports
import_wc_orders();
$email .= timer("import_wc_orders", $time);

import_cp_orders(); //Put this after wc_orders so that we never have an wc_order without a matching cp_order
$email .= timer("import_cp_orders", $time);

import_cp_order_items(); //Put this after orders so that we never have an order without a matching order_item
$email .= timer("import_cp_order_items", $time);

import_cp_patients(); //Put this after orders so that we never have an order without a matching patient
$email .= timer("import_cp_patients", $time);

import_wc_patients(); //Put this after cp_patients so that we always import all new cp_patients first, so that out wc_patient created feed does not give false positives
$email .= timer("import_wc_patients", $time);

import_cp_rxs_single(); //Put this after order_items so that we never have an order item without a matching rx
$email .= timer("import_cp_rxs_single", $time);

import_v2_stock_by_month(); //Put this after rxs so that we never have a rxs without a matching stock level
$email .= timer("import_v2_stock_by_month", $time);

import_v2_drugs(); //Put this after rxs so that we never have a rxs without a matching drug
$email .= timer("import_v2_drugs", $time);

//Updates (Mirror Ordering of the above - not sure how necessary this is)
update_drugs();
$email .= timer("update_drugs", $time);

update_stock_by_month();
$email .= timer("update_stock_by_month", $time);

update_rxs_single();
$email .= timer("update_rxs_single", $time);

update_patients_cp();
$email .= timer("update_patients_cp", $time);

update_patients_wc();
$email .= timer("update_patients_wc", $time);

update_order_items();
$email .= timer("update_order_items", $time);

update_orders_cp();
$email .= timer("update_orders_cp", $time);

update_orders_wc();
$email .= timer("update_orders_wc", $time);

watch_invoices();
$email .= timer("watch_invoices", $time);

if ($email) {
  log_notice("WebForm CRON Finished", $email);
  mail(DEBUG_EMAIL, "Log Notices", log_notices(), "From: webform@goodpill.org\r\n");
}
