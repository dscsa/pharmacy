<?php
/*
 TODO We will want to come up with a better solution than the replace table.
       This is going to hit memory limits fairly frequently.  A better solution
       would be a queue based system that we can create a ladder pattern of jobs
       and process quickly and near realtime.
 */

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';

/*
  General Requires
 */

require_once 'keys.php';
require_once 'helpers/helper_log.php';
require_once 'helpers/helper_constants.php';

/*
  Import Functions - Used to pull data into summary tables
  with normalized formatting
 */
require_once 'imports/import_v2_drugs.php';
require_once 'imports/import_v2_stock_by_month.php';
require_once 'imports/import_cp_rxs_single.php';
require_once 'imports/import_wc_patients.php';
require_once 'imports/import_cp_patients.php';
require_once 'imports/import_cp_order_items.php';
require_once 'imports/import_wc_orders.php';
require_once 'imports/import_cp_orders.php';

/*
  Export Functions - used to push aggregate data out and to notify
  users of interactions
 */
require_once 'updates/update_drugs.php';
require_once 'updates/update_stock_by_month.php';
require_once 'updates/update_rxs_single.php';
require_once 'updates/update_patients_wc.php';
require_once 'updates/update_patients_cp.php';
require_once 'updates/update_order_items.php';
require_once 'updates/update_orders_wc.php';
require_once 'updates/update_orders_cp.php';

/**
 * Sometimes the job can run too long and overlap with the next execution.
 * We should log this error so we can keep track of how frequently it happens
 *
 * NOTE https://stackoverflow.com/questions/10552016/how-to-prevent-the-cron-job-execution-if-it-is-already-running
 */
$f = fopen('readme.md', 'w') or log_error('Webform Cron Job Cannot Create Lock File');

if ( ! flock($f, LOCK_EX | LOCK_NB)) {
  return log_error('Skipping Webform Cron Job Because Previous One Is Still Running');
}

try {
  timer("", $time);
  $email = '';

  /**
   * Import Orders from WooCommerce (an actual shippment) and store it in
   * a summary table
   *
   * TABLE
   *
   * TODO we can currently have a CP order without a matching WC order,
   *      in these cases the WC order will show up in the "deleted" feed
   */
  import_wc_orders();
  $email .= timer("import_wc_orders", $time);

  /**
   * Pull a list of the CarePoint Orders (an actual shipment) and store it in mysql
   *
   * TABLE gp_orders_cp
   *
   * NOTE Put this after wc_orders so that we never have an wc_order
   *      without a matching cp_order
   */
  import_cp_orders(); //
  $email .= timer("import_cp_orders", $time);

  /**
   * Pull all the ordered items(perscribed medication) from CarePoint
   * and put it into a table in mysql
   *
   * TABLE gp_order_items_cp
   *
   * NOTE Put this after orders so that we never have an order without a
   *      matching order_item
   */
  import_cp_order_items(); //
  $email .= timer("import_cp_order_items", $time);

  /**
   *
   * Copies all the patient data out of sharepoint and into the mysql table
   *
   * TABLE gp_patients_cp
   *
   * NOTE Put this after orders so that we never have an order without a matching patient
   */
  import_cp_patients();
  $email .= timer("import_cp_patients", $time);

  /**
   * Pull all the patiens/users out of woocommerce and put them into the mysql tables
   *
   * TABLE gp_patients_wc
   *
   * NOTE We get all users, but spcificlly we pull out users that have birthdates
   *      below 1900 and above 2100. Assuming these are invalid birthdates
   *      and not usable?
   *
   * NOTE Put this after cp_patients so that we always import all new cp_patients
   *      first, so that out wc_patient created feed does not give false positives
   */
  import_wc_patients();
  $email .= timer("import_wc_patients", $time);

  /**
   * Get the RX details out of CarePoint and put into the mysql table
   *
   * TABLE gp_rxs_single_cp
   *
   * NOTE Put this after order_items so that we never have an order item without
   *  a matching rx
   */
  import_cp_rxs_single();
  $email .= timer("import_cp_rxs_single", $time);

  /**
   * Import stock levels for this month and the 2 previous months.  Store this in
   *
   * TABLE gp_stock_by_month_v2
   *
   * NOTE Put this after rxs so that we never have a rxs without a matching stock level
   */
  import_v2_stock_by_month();
  $email .= timer("import_v2_stock_by_month", $time);

  /**
   * Get all the possible drugs from v2 and put them into
   *
   * TABLE gp_drugs_v2
   *
   * NOTE Put this after rxs so that we never have a rxs without a matching drug
   */
  import_v2_drugs();
  $email .= timer("import_v2_drugs", $time);

  /*
    Importing and Normalizing are done.  Now we will start to push data
    and communications
   */

  /**
   * Retrieve all the drugs and CRUD the changes from v2 to the gp database
   *
   * NOTE Updates (Mirror Ordering of the above - not sure how necessary this is)
   */
  update_drugs();
  $email .= timer("update_drugs", $time);

  /**
   * Bring in the inventory and put it into the live stock table
   * then update the monthly stock table with those metrics
   *
   * TABLE gp_stock_live
   * TABLE gp_stock_by_month
   *
   */
  update_stock_by_month();
  $email .= timer("update_stock_by_month", $time);

  /**
   * [update_rxs_single description]
   * @var [type]
   */
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
    //mail(DEBUG_EMAIL, "Log Notices", log_notices(), "From: webform@goodpill.org\r\n");
  }
} catch (Exception $e) {
  log_error('Webform Cron Job Fatal Error', $e);
}
