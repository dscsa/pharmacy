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
require_once 'helpers/helper_logger.php';
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


$execution_details = ['start' => date('c')];

$f = fopen('readme.md', 'w') or log_error('Webform Cron Job Cannot Create Lock File');

if (! flock($f, LOCK_EX | LOCK_NB)) {
    SirumLog::error('Skipping Webform Cron Job Because Previous One Is Still Running', $execution_details);
    exit;
}

try {
    $start = microtime(true);
    $execution_details['timers'] = [];
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
    $execution_details['timers']['import_wc_orders'] = ceil(microtime(true) - $start);

    /**
     * Pull a list of the CarePoint Orders (an actual shipment) and store it in mysql
     *
     * TABLE gp_orders_cp
     *
     * NOTE Put this after wc_orders so that we never have an wc_order
     *      without a matching cp_order
     */
    import_cp_orders(); //
    $execution_details['timers']['import_cp_orders'] = ceil(microtime(true) - $start);

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
    $execution_details['timers']['import_cp_order_items'] = ceil(microtime(true) - $start);

    /**
     *
     * Copies all the patient data out of sharepoint and into the mysql table
     *
     * TABLE gp_patients_cp
     *
     * NOTE Put this after orders so that we never have an order without a matching patient
     */
    import_cp_patients();
    $execution_details['timers']['import_cp_patients'] = ceil(microtime(true) - $start);

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
    $execution_details['timers']['import_wc_patients'] = ceil(microtime(true) - $start);

    /**
     * Get the RX details out of CarePoint and put into the mysql table
     *
     * TABLE gp_rxs_single_cp
     *
     * NOTE Put this after order_items so that we never have an order item without
     *  a matching rx
     */
    import_cp_rxs_single();
    $execution_details['timers']['import_cp_rxs_single'] = ceil(microtime(true) - $start);

    /**
     * Import stock levels for this month and the 2 previous months.  Store this in
     *
     * TABLE gp_stock_by_month_v2
     *
     * NOTE Put this after rxs so that we never have a rxs without a matching stock level
     */
    import_v2_stock_by_month();
    $execution_details['timers']['import_v2_stock_by_month'] = ceil(microtime(true) - $start);

    /**
     * Get all the possible drugs from v2 and put them into
     *
     * TABLE gp_drugs_v2
     *
     * NOTE Put this after rxs so that we never have a rxs without a matching drug
     */
    import_v2_drugs();
    $execution_details['timers']['import_v2_drugs'] = ceil(microtime(true) - $start);

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
    $execution_details['timers']['update_drugs'] = ceil(microtime(true) - $start);

    /**
     * Bring in the inventory and put it into the live stock table
     * then update the monthly stock table with those metrics
     *
     * TABLE gp_stock_live
     * TABLE gp_stock_by_month
     *
     */
    update_stock_by_month();
    $execution_details['timers']['update_stock_by_month'] = ceil(microtime(true) - $start);

    /**
     * [update_rxs_single description]
     * @var [type]
     */
    update_rxs_single();
    $execution_details['timers']['update_rxs_single'] = ceil(microtime(true) - $start);

    update_patients_cp();
    $execution_details['timers']['update_patients_cp'] = ceil(microtime(true) - $start);

    update_patients_wc();
    $execution_details['timers']['update_patients_wc'] = ceil(microtime(true) - $start);

    update_order_items();
    $execution_details['timers']['update_order_items'] = ceil(microtime(true) - $start);

    update_orders_cp();
    $execution_details['timers']['update_orders_cp'] = ceil(microtime(true) - $start);

    update_orders_wc();
    $execution_details['timers']['update_orders_wc'] = ceil(microtime(true) - $start);

    watch_invoices();
    $execution_details['timers']['watch_invoices'] = ceil(microtime(true) - $start);


    $execution_details['timers']['total'] = array_sum($execution_details['timers']);
    $execution_details                    = ['end' => date('c')];

    SirumLog::info('Pharmacy Automation Complete', $execution_details);
} catch (Exception $e) {
    $execution_details['e'] = $e;
    SirumLog::alet('Webform Cron Job Fatal Error', $execution_details);
}
