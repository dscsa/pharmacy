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

$start_time = microtime(true);

require_once 'vendor/autoload.php';
require_once 'helpers/helper_error_handler.php';

use Sirum\Logging\SirumLog;
use Sirum\Logging\AuditLog;
use Sirum\Logging\CliLog;

use Sirum\Utilities\Timer;

/*
  General Requires
 */

require_once 'keys.php';
require_once 'helpers/helper_logger.php';
require_once 'helpers/helper_log.php';
require_once 'helpers/helper_logger.php';
require_once 'helpers/helper_constants.php';
require_once 'helpers/helper_cp_test.php';

/*
    Test the Carepoint connection and fail
 */

if (!cp_test()) {
    $message = '** Could not connect to Carepoint **';
    echo "{$message}\n";
    SirumLog::alert($message);
    CliLog::alert($message);
    SirumLog::getLogger()->flush();
    exit;
}

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
  Change Functions - Used to compared new tables with current tables
  return an [created, updated, deleted] and set current tables to be same as new tables
 */
 require_once 'changes/changes_to_drugs.php';
 require_once 'changes/changes_to_stock_by_month.php';
 require_once 'changes/changes_to_rxs_single.php';
 require_once 'changes/changes_to_patients_wc.php';
 require_once 'changes/changes_to_patients_cp.php';
 require_once 'changes/changes_to_order_items.php';
 require_once 'changes/changes_to_orders_wc.php';
 require_once 'changes/changes_to_orders_cp.php';

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


$global_exec_details = ['start' => date('c')];

$f = fopen('/tmp/pharmacy.lock', 'w') or log_error('Webform Cron Job Cannot Create Lock File');

if (!flock($f, LOCK_EX | LOCK_NB)) {
    $still_running = "\n*** Warning Webform Cron Job Because Previous One Is Still Running ***\n\n";
    echo $still_running;
    SirumLog::notice($still_running, $global_exec_details);
    CliLog::notice($still_running);

    // Push any lagging logs to google Cloud
    SirumLog::getLogger()->flush();

    exit;
}

//This is used to invalidate cache for patient portal BUT since this cron job can take several minutes to run
//where we create the timestamp (before import, after imports, after updates) can make a difference and potentially
//cause data inconsistency between guardian, gp_tables, and the patient portal.  For example if the patient portal
//makes a change to guardian while cronjob is running - the pharmacy app import will have already happened
//this means the gp_tables will get old data AND the cache will be invlidated if the timestamp is not created until the end of the script
//For this reason AK on 2020-12-04 thinks the timestamp should be at beginning of script
file_put_contents('/goodpill/webform/pharmacy-run.txt', mktime());

try {
    $global_exec_details['timers']       = [];
    $global_exec_details['timers_gd']    = [];
    $global_exec_details['timers_loops'] = [];

    CliLog::info("Starting syncing.php. Importing data from sources:");

    /**
     * Import Orders from WooCommerce (an actual shippment) and store it in
     * a summary table
     *
     * TABLE
     *
     * TODO we can currently have a CP order without a matching WC order,
     *      in these cases the WC order will show up in the "deleted" feed
     */
    CliLog::info("Start importing WooCommerce orders");
    CliLog::info("Import WC Orders started");
    Timer::start("Import WC Orders");
    import_wc_orders();
    Timer::stop("Import WC Orders");
    CliLog::info("Completed in " . Timer::read('Import WC Orders', Timer::FORMAT_HUMAN));


    /**
     * Pull a list of the CarePoint Orders (an actual shipment) and store it in mysql
     *
     * TABLE gp_orders_cp
     *
     * NOTE Put this after wc_orders so that we never have an wc_order
     *      without a matching cp_order
     */
    CliLog::info("Import CP Orders started");
    Timer::start("Import CP Orders");
    import_cp_orders(); //
    Timer::stop("Import CP Orders");
    CliLog::info("Completed in " . Timer::read('Import CP Orders', Timer::FORMAT_HUMAN));

    /**
     * Pull all the ordered items(perscribed medication) from CarePoint
     * and put it into a table in mysql
     *
     * TABLE gp_order_items_cp
     *
     * NOTE Put this after orders so that we never have an order without a
     *      matching order_item
     */
    CliLog::info("Import CP Order Items started");
    Timer::start("Import CP Order Items");
    import_cp_order_items(); //
    Timer::stop("Import CP Order Items");
    CliLog::info("Completed in " . Timer::read('Import CP Order Items', Timer::FORMAT_HUMAN));

    /**
     *
     * Copies all the patient data out of sharepoint and into the mysql table
     *
     * TABLE gp_patients_cp
     *
     * NOTE Put this after orders so that we never have an order without a matching patient
     */
    CliLog::info("Import CP Patients started");
    Timer::start("Import CP Patients");
    import_cp_patients();
    Timer::stop("Import CP Patients");
    CliLog::info("Completed in " . Timer::read('Import CP Patients', Timer::FORMAT_HUMAN));

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
    CliLog::info("Import WC Patients started");
    Timer::start("Import WC Patients");
    import_wc_patients();
    Timer::stop("Import WC Patients");
    CliLog::info("Completed in " . Timer::read('Import WC Patients', Timer::FORMAT_HUMAN));

    /**
     * Get the RX details out of CarePoint and put into the mysql table
     *
     * TABLE gp_rxs_single_cp
     *
     * NOTE Put this after order_items so that we never have an order item without
     *  a matching rx
     */
    CliLog::info("Import Rxs Single started");
    Timer::start("Import Rxs Single");
    import_cp_rxs_single();
    Timer::stop("Import Rxs Single");
    CliLog::info("Completed in " . Timer::read('Import Rxs Single', Timer::FORMAT_HUMAN));

    /**
     * Import stock levels for this month and the 2 previous months.  Store this in
     *
     * TABLE gp_stock_by_month_v2
     *
     * NOTE Put this after rxs so that we never have a rxs without a matching stock level
     */
    CliLog::info("Import v2 Stock by Month started");
    Timer::start("Import v2 Stock by Month");
    import_v2_stock_by_month();
    Timer::stop("Import v2 Stock by Month");
    CliLog::info("Completed in " . Timer::read('Import v2 Stock by Month', Timer::FORMAT_HUMAN));

    /**
     * Get all the possible drugs from v2 and put them into
     *
     * TABLE gp_drugs_v2
     *
     * NOTE Put this after rxs so that we never have a rxs without a matching drug
     */
    CliLog::info("Import v2 Drugs started");
    Timer::start("Import v2 Drugs");
    import_v2_drugs();
    Timer::stop("Import v2 Drugs");
    CliLog::info("Completed in " . Timer::read('Import v2 Drugs', Timer::FORMAT_HUMAN));

    echo "\nAll Data Imported. Starting Change Detection:\n";
    /*
      Now we will update tables to new data and determine change feeds
     */

    CliLog::info("Changes Drugs started");
    Timer::start("Changes Drugs");
    $changes_to_drugs = changes_to_drugs("gp_drugs_v2");
    Timer::stop("Changes Drugs");
    CliLog::info("Completed in " . Timer::read('Changes Drugs', Timer::FORMAT_HUMAN));

    CliLog::info("Changes Stock by Month started");
    Timer::start("Changes Stock by Month");
    $changes_to_stock_by_month = changes_to_stock_by_month("gp_stock_by_month_v2");
    Timer::stop("Changes Stock by Month");
    CliLog::info("Completed in " . Timer::read('Changes Stock by Month', Timer::FORMAT_HUMAN));

    CliLog::info("Changes Rxs Single started");
    Timer::start("Changes Rxs Single");
    $changes_to_rxs_single = changes_to_rxs_single("gp_rxs_single_cp");
    Timer::stop("Changes Rxs Single");
    CliLog::info("Completed in " . Timer::read('Changes Rxs Single', Timer::FORMAT_HUMAN));

    CliLog::info("Changes CP Patients started");
    Timer::start("Changes CP Patients");
    $changes_to_patients_cp = changes_to_patients_cp("gp_patients_cp");
    Timer::stop("Changes CP Patients");
    CliLog::info("Completed in " . Timer::read('Changes CP Patients', Timer::FORMAT_HUMAN));

    CliLog::info("Changes WC Patients started");
    Timer::start("Changes WC Patients");
    $changes_to_patients_wc = changes_to_patients_wc("gp_patients_wc");
    Timer::stop("Changes WC Patients");
    CliLog::info("Completed in " . Timer::read('Changes WC Patients', Timer::FORMAT_HUMAN));

    CliLog::info("Changes Order Items started");
    Timer::start("Changes Order Items");
    $changes_to_order_items = changes_to_order_items('gp_order_items_cp');
    Timer::stop("Changes Order Items");
    CliLog::info("Completed in " . Timer::read('Changes Order Items', Timer::FORMAT_HUMAN));

    CliLog::info("Changes CP Orders started");
    Timer::start("Changes CP Orders");
    $changes_to_orders_cp = changes_to_orders_cp("gp_orders_cp");
    Timer::stop("Changes CP Orders");
    CliLog::info("Completed in " . Timer::read('Changes CP Orders', Timer::FORMAT_HUMAN));

    CliLog::info("Changes WC Orders started");
    Timer::start("Changes WC Orders");
    $changes_to_orders_wc = changes_to_orders_wc("gp_orders_wc");
    Timer::stop("Changes WC Orders");
    CliLog::info("Completed in " . Timer::read('Changes WC Orders', Timer::FORMAT_HUMAN));

    echo "\nAll Changes Detected & Tables Updated. Starting Updates:\n";
    /*
     Now we will to trigger side effects based on changes
    */

    //We can spin up a new PHP process now without conflicts, don't need to wait
    //There are some DB changes after this point so there could be some undefined
    //behavior from this.  BUT the update loops after this point are very slow
    //so until we get everything faster it's worth the risk
    flock($f, LOCK_UN | LOCK_NB);

    /**
     * Retrieve all the drugs and CRUD the changes from v2 to the gp database
     *
     * NOTE Updates (Mirror Ordering of the above - not sure how necessary this is)
     */
    CliLog::info("Update Drugs started");
    Timer::start("update.drugs");
    update_drugs($changes_to_drugs);
    Timer::stop("update.drugs");
    CliLog::info("Completed in " . Timer::read('update.drugs', Timer::FORMAT_HUMAN));

    /**
     * Bring in the inventory and put it into the live stock table
     * then update the monthly stock table with those metrics
     *
     * TABLE gp_stock_live
     * TABLE gp_stock_by_month
     *
     */
    CliLog::info("Update Stock by Month started");
    Timer::start("update.stock.month");
    update_stock_by_month($changes_to_stock_by_month);
    Timer::stop("update.stock.month");
    CliLog::info("Completed in " . Timer::read('update.stock.month', Timer::FORMAT_HUMAN));
    /**
     * [update_rxs_single description]
     * @var [type]
     */

    CliLog::info("Update CP Patients started");
    Timer::start("update.patients.cp");
    update_patients_cp($changes_to_patients_cp);
    Timer::stop("update.patients.cp");
    CliLog::info("Completed in " . Timer::read('update.patients.cp', Timer::FORMAT_HUMAN));

    CliLog::info("Update WC Patients started");
    Timer::start("update.patients.wc");
    update_patients_wc($changes_to_patients_wc);
    Timer::stop("update.patients.wc");
    CliLog::info("Completed in " . Timer::read('update.patients.wc', Timer::FORMAT_HUMAN));

    //Run before cp-order and order-items to make sure that rx-grouped is upto date when doing load_full_order/item
    CliLog::info("Update Rxs Single started");
    Timer::start("update.rxs");
    update_rxs_single($changes_to_rxs_single);
    Timer::stop("update.rxs");
    CliLog::info("Completed in " . Timer::read('update.rxs', Timer::FORMAT_HUMAN));

    CliLog::info("Update CP Orders started");
    Timer::start("update.patients.cp");
    update_orders_cp($changes_to_orders_cp);
    Timer::stop("update.patients.cp");
    CliLog::info("Completed in " . Timer::read('update.patients.cp', Timer::FORMAT_HUMAN));

    CliLog::info("Update WC Orders started");
    Timer::start("update.orders.wc");
    update_orders_wc($changes_to_orders_wc);
    Timer::stop("update.orders.wc");
    CliLog::info("Completed in " . Timer::read('update.orders.wc', Timer::FORMAT_HUMAN));

    //Run this after orders-cp loop so that we can sync items to the order and remove duplicate GSNs first,
    //rather than doing stuff in this loop that we undo in the orders-cp loop
    CliLog::info("Update Order Items started");
    Timer::start("update.order.items");
    update_order_items($changes_to_order_items);
    Timer::stop("update.order.items");
    CliLog::info("Completed in " . Timer::read('update.order.items', Timer::FORMAT_HUMAN));

    echo "Watch Invoices ";
    Timer::start('Watch Invoices');
    watch_invoices();
    Timer::stop('Watch Invoices');
    CliLog::info("Completed in " . Timer::read('Watch Invoices', Timer::FORMAT_HUMAN));


    $global_exec_details['timers']['total']      = array_sum($global_exec_details['timers']);
    $global_exec_details['timers_gd']['total']   = array_sum($global_exec_details['timers_gd']);
    $global_exec_details['timers_gd']['merge']   = $gd_merge_timers;
    $global_exec_details['timers_gd']['percent'] = ceil($global_exec_details['timers_gd']['total']/$global_exec_details['timers']['total']*100);
    $global_exec_details['end']                  = date('c');

    $exec_message = sprintf(
        "Pharmacy Automation Complete in %s seconds starting at %s",
        $global_exec_details['timers']['total'],
        date('c', $start_time)
    );

    // If the script takes more than 10 minutes,
    // then we are taking too long and it needs to be an error
    if ($global_exec_details['timers']['total'] > 600) {
        SirumLog::error($exec_message, $global_exec_details);
    } else {
        SirumLog::info($exec_message, $global_exec_details);
    }


    CliLog::info("All data processed {$global_exec_details['end']}");

    $global_exec_details['timers']['total'] = ceil(microtime(true) - $start_time);
    print_r($global_exec_details);
} catch (Exception $e) {
    $global_exec_details['error_message'] = $e->getMessage;
    SirumLog::alert('Webform Cron Job Fatal Error', $global_exec_details);
    throw $e;
}

$timers = asort(Timer::getTimers());

CliLog::debug("Timers");
foreach ($timers as $timer) {
    printf(
        "    %s: %s\n",
        $timer,
        Timer::read($timer, Timer::FORMAT_HUMAN)
    );
}

// Push any lagging logs to google Cloud
SirumLog::getLogger()->flush();
echo "Pharmacy Automation Success in {$global_exec_details['timers']['total']} seconds\n";
