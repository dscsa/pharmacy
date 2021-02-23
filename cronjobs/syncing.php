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

use GoodPill\Logging\GPLog;
use GoodPill\Logging\AuditLog;
use GoodPill\Logging\CliLog;

use GoodPill\Utilities\Timer;
use GoodPill\AWS\SQS\FullChangeQueue;

/**
 * Defining this here as a temporary.  Ultimatly this will move out somewhere else,
 * but not sure yet where
 * @param  string $changes_to   What Changes are being made
 * @param  array  $change_types An array of the changes to include in the request
 *      example: ['deleted']
 *      example: ['created', 'updated']
 * @param  array  $changes      The full array of the changes
 * @return PharmacySyncRequest
 */
function get_sync_request(string $changes_to, array $change_types, array $changes)
{
    $syncing_request               = new PharmacySyncRequest();
    $syncing_request->changes_to   = $changes_to;

    $included_changes = array_intersect_key(
        $changes,
        array_flip($change_types)
    );

    $syncing_request->changes  = $included_changes;
    $syncing_request->group_id = 'linear-sync';
    return $syncing_request;
}

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
    GPLog::alert($message);
    CliLog::alert($message);
    GPLog::getLogger()->flush();
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
    GPLog::notice($still_running, $global_exec_details);
    CliLog::notice($still_running);

    // Push any lagging logs to google Cloud
    GPLog::getLogger()->flush();

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
    Timer::start("import.v2.drugs");
    import_v2_drugs();
    Timer::stop("import.v2.drugs");
    CliLog::info("Completed in " . Timer::read('import.v2.drugs', Timer::FORMAT_HUMAN));

    echo "\nAll Data Imported. Starting Change Detection:\n";
    /*
      Now we will update tables to new data and determine change feeds
     */

    CliLog::info("Changes Drugs started");
    Timer::start("changes.drugs");
    $changes_to_drugs = changes_to_drugs("gp_drugs_v2");
    Timer::stop("changes.drugs");
    CliLog::info("Completed in " . Timer::read('changes.drugs', Timer::FORMAT_HUMAN));

    CliLog::info("Changes Stock by Month started");
    Timer::start("changes.stock.month");
    $changes_to_stock_by_month = changes_to_stock_by_month("gp_stock_by_month_v2");
    Timer::stop("changes.stock.month");
    CliLog::info("Completed in " . Timer::read('changes.stock.month', Timer::FORMAT_HUMAN));

    CliLog::info("Changes Rxs Single started");
    Timer::start("changes.rxs");
    $changes_to_rxs_single = changes_to_rxs_single("gp_rxs_single_cp");
    Timer::stop("changes.rxs");
    CliLog::info("Completed in " . Timer::read('changes.rxs', Timer::FORMAT_HUMAN));

    CliLog::info("Changes CP Patients started");
    Timer::start("changes.patients.cp");
    $changes_to_patients_cp = changes_to_patients_cp("gp_patients_cp");
    Timer::stop("changes.patients.cp");
    CliLog::info("Completed in " . Timer::read('changes.patients.cp', Timer::FORMAT_HUMAN));

    CliLog::info("Changes WC Patients started");
    Timer::start("changes.patients.wc");
    $changes_to_patients_wc = changes_to_patients_wc("gp_patients_wc");
    Timer::stop("changes.patients.wc");
    CliLog::info("Completed in " . Timer::read('changes.patients.wc', Timer::FORMAT_HUMAN));

    CliLog::info("Changes Order Items started");
    Timer::start("changes.orders.items");
    $changes_to_order_items = changes_to_order_items('gp_order_items_cp');
    Timer::stop("changes.orders.items");
    CliLog::info("Completed in " . Timer::read('changes.orders.items', Timer::FORMAT_HUMAN));

    CliLog::info("Changes CP Orders started");
    Timer::start("changes.orders.cp");
    $changes_to_orders_cp = changes_to_orders_cp("gp_orders_cp");
    Timer::stop("changes.orders.cp");
    CliLog::info("Completed in " . Timer::read('changes.orders.cp', Timer::FORMAT_HUMAN));

    CliLog::info("Changes WC Orders started");
    Timer::start("changes.orders.wc");
    $changes_to_orders_wc = changes_to_orders_wc("gp_orders_wc");
    Timer::stop("changes.orders.wc");
    CliLog::info("Completed in " . Timer::read('changes.orders.wc', Timer::FORMAT_HUMAN));

    echo "\nAll Changes Detected & Tables Updated. Starting Updates:\n";
    /*
     Now we will to trigger side effects based on changes
    */

    //We can spin up a new PHP process now without conflicts, don't need to wait
    //There are some DB changes after this point so there could be some undefined
    //behavior from this.  BUT the update loops after this point are very slow
    //so until we get everything faster it's worth the risk
    flock($f, LOCK_UN | LOCK_NB);

    /*
        WARNING - Order of operations is important.
        Do not change the order things are queued
        Wrap these in a try catch so they don't break anything
     */
    try {
         $changes_sqs_messages = [];

         // Drugs
         $changes_sqs_messages[] = get_sync_request(
             'drugs',
             ['created'],
             $changes_to_drugs
         );

         $changes_sqs_messages[] = get_sync_request(
             'drugs',
             ['updated'],
             $changes_to_drugs
         );

         // $changes_to_stock_by_month
         $changes_sqs_messages[] = get_sync_request(
             'stock_by_month',
             ['created', 'deleted', 'updated'],
             $changes_to_stock_by_month
         );

         // Patient CP
         $changes_sqs_messages[] = get_sync_request(
             'patients_cp',
             ['updated'],
             $changes_to_patients_cp
         );

         // Patient WC
         $changes_sqs_messages[] = get_sync_request(
             'patients_wc',
             ['created'],
             $changes_to_patients_wc
         );
         $changes_sqs_messages[] = get_sync_request(
             'patients_wc',
             ['deleted'],
             $changes_to_patients_wc
         );
         $changes_sqs_messages[] = get_sync_request(
             'patients_wc',
             ['updated'],
             $changes_to_patients_wc
         );

         // Rxs Single
         $changes_sqs_messages[] = get_sync_request(
             'rxs_single',
             ['created', 'updated'],
             $changes_to_rxs_single
         );
         $changes_sqs_messages[] = get_sync_request(
             'rxs_single',
             ['deleted'],
             $changes_to_rxs_single
         );

         // Orders CP
         $changes_sqs_messages[] = get_sync_request(
             'orders_cp',
             ['created'],
             $changes_to_orders_cp
         );
         $changes_sqs_messages[] = get_sync_request(
             'orders_cp',
             ['deleted'],
             $changes_to_orders_cp
         );
         $changes_sqs_messages[] = get_sync_request(
             'orders_cp',
             ['updated'],
             $changes_to_orders_cp
         );

         // Orders WC
         $changes_sqs_messages[] = get_sync_request(
             'orders_wc',
             ['created'],
             $changes_to_orders_wc
         );
         $changes_sqs_messages[] = get_sync_request(
             'orders_wc',
             ['deleted'],
             $changes_to_orders_wc
         );
         $changes_sqs_messages[] = get_sync_request(
             'orders_wc',
             ['updated'],
             $changes_to_orders_wc
         );

         // Orders WC
         $changes_sqs_messages[] = get_sync_request(
             'order_items',
             ['created'],
             $changes_to_order_items
         );
         $changes_sqs_messages[] = get_sync_request(
             'order_items',
             ['deleted'],
             $changes_to_order_items
         );
         $changes_sqs_messages[] = get_sync_request(
             'order_items',
             ['updated'],
             $changes_to_order_items
         );

         $changeq = new PharmacySyncQueue();
         $changeq->sendBatch($changes_sqs_messages);
    } catch (\Exception $e) {
         $message = "Pharmacy App - PHP Uncaught Exception ";
         $message .= $e->getCode() . " " . $e->getMessage() ." ";
         $message .= $e->getFile() . ":" . $e->getLine() . "\n";
         $message .= $e->getTraceAsString();
         echo "$message";
         GPLog::critical($message);
    }

    echo "Watch Invoices ";
    Timer::start('Watch Invoices');
    watch_invoices();
    Timer::stop('Watch Invoices');
    CliLog::info("Completed in " . Timer::read('Watch Invoices', Timer::FORMAT_HUMAN));

    $global_exec_details['timers']['total']      = ceil(microtime(true) - $start_time);
    $global_exec_details['end']                  = date('c');

    $exec_message = sprintf(
        "Pharmacy Automation Complete in %s seconds starting at %s",
        $global_exec_details['timers']['total'],
        date('c', $start_time)
    );

    // If the script takes more than 10 minutes,
    // then we are taking too long and it needs to be an error
    if ($global_exec_details['timers']['total'] > 600) {
        GPLog::error($exec_message, $global_exec_details);
    } else {
        GPLog::info($exec_message, $global_exec_details);
    }
} catch (Exception $e) {
    $global_exec_details['error_message'] = $e->getMessage;
    GPLog::alert('Webform Cron Job Fatal Error', $global_exec_details);
    throw $e;
}

$timers = Timer::getTimers();
asort($timers);

CliLog::debug("Timers");
foreach ($timers as $timer) {
    printf(
        "    %s: %s\n",
        $timer,
        Timer::read($timer, Timer::FORMAT_HUMAN)
    );
}

// Push any lagging logs to google Cloud
GPLog::getLogger()->flush();
echo "Pharmacy Automation Success in {$global_exec_details['timers']['total']} seconds\n";
