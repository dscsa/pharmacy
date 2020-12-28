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

use Sirum\Logging\SirumLog;

/*
  General Requires
 */

require_once 'keys.php';
require_once 'helpers/helper_logger.php';
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

echo "All Includes are here \n";

$start = microtime(true);

echo date('c') . "\n";

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
echo "import_wc_orders complete\n";


/**
 * Pull a list of the CarePoint Orders (an actual shipment) and store it in mysql
 *
 * TABLE gp_orders_cp
 *
 * NOTE Put this after wc_orders so that we never have an wc_order
 *      without a matching cp_order
 */
import_cp_orders();
echo "import_cp_orders complete\n";

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
echo "import_cp_order_items complete\n";

/**
 *
 * Copies all the patient data out of sharepoint and into the mysql table
 *
 * TABLE gp_patients_cp
 *
 * NOTE Put this after orders so that we never have an order without a matching patient
 */
import_cp_patients();
echo "import_cp_patients complete\n";


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
echo "import_wc_patients complete\n";

/**
 * Get the RX details out of CarePoint and put into the mysql table
 *
 * TABLE gp_rxs_single_cp
 *
 * NOTE Put this after order_items so that we never have an order item without
 *  a matching rx
 */
import_cp_rxs_single();
echo "import_cp_rxs_single complete\n";

/**
 * Import stock levels for this month and the 2 previous months.  Store this in
 *
 * TABLE gp_stock_by_month_v2
 *
 * NOTE Put this after rxs so that we never have a rxs without a matching stock level
 */
import_v2_stock_by_month();
echo "import_v2_stock_by_month complete\n";

/**
 * Get all the possible drugs from v2 and put them into
 *
 * TABLE gp_drugs_v2
 *
 * NOTE Put this after rxs so that we never have a rxs without a matching drug
 */
import_v2_drugs();
echo "import_v2_drugs complete\n";
echo "Moving to Updates/Exports\n";

/**
 * Retrieve all the drugs and CRUD the changes from v2 to the gp database
 *
 * NOTE Updates (Mirror Ordering of the above - not sure how necessary this is)
 */
update_drugs();
$execution_details['timers']['update_drugs'] = ceil(microtime(true) - $start);
echo "update_drugs complete\n";

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
echo "Update Stock By Month Complete\n";
/**
 * [update_rxs_single description]
 * @var [type]
 */
update_rxs_single();
$execution_details['timers']['update_rxs_single'] = ceil(microtime(true) - $start);
echo "Update RXs Complete\n";

update_patients_cp();
$execution_details['timers']['update_patients_cp'] = ceil(microtime(true) - $start);
echo "Update CP Patients Complete\n";

update_patients_wc();
$execution_details['timers']['update_patients_wc'] = ceil(microtime(true) - $start);
echo "Update WC Patients Complete\n";

update_order_items();
$execution_details['timers']['update_order_items'] = ceil(microtime(true) - $start);
echo "Update Orders Items Complete\n";

update_orders_cp();
$execution_details['timers']['update_orders_cp'] = ceil(microtime(true) - $start);
echo "Update CP Orders Complete\n";

update_orders_wc();
$execution_details['timers']['update_orders_wc'] = ceil(microtime(true) - $start);
echo "Update WC Orders Complete\n";
//
watch_invoices();
$execution_details['timers']['watch_invoices'] = ceil(microtime(true) - $start);
echo "Watch Invoices Complete\n";
echo date('c') . "\n";
