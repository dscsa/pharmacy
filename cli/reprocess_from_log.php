<?php
ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

define('CONSOLE_LOGGING', 1);

require_once 'vendor/autoload.php';

require_once 'keys.php';
require_once 'helpers/helper_logger.php';
require_once 'helpers/helper_log.php';
require_once 'helpers/helper_logger.php';
require_once 'helpers/helper_constants.php';
require_once 'helpers/helper_cp_test.php';

require_once 'dbs/mysql_wc.php';
require_once 'dbs/mssql_cp.php';

require_once 'updates/update_drugs.php';
require_once 'updates/update_stock_by_month.php';
require_once 'updates/update_rxs_single.php';
require_once 'updates/update_patients_wc.php';
require_once 'updates/update_patients_cp.php';
require_once 'updates/update_order_items.php';
require_once 'updates/update_orders_wc.php';
require_once 'updates/update_orders_cp.php';


use Google\Cloud\Logging\LoggingClient;

if (file_exists('/etc/google/unified-logging.json')) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=/etc/google/unified-logging.json');
} elseif (file_exists('/goodpill/webform/unified-logging.json')) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=/goodpill/webform/unified-logging.json');
}

function printHelp()
{
    echo <<<EOF
This script will automatically reproccess a specific log entry.  You can only
process one entry at a time.  Pass in the insert_id of a google log entry that
has the name data-.  The script will pull out the data and call the appropriate
function.  You will be presented with a confirmation screen before the change
is processed

php reproccess_from_log.php -i ihdiwne612
    -i            The insert_id for a google log entry.
    -h print this message

EOF;
    exit;
}

$args   = getopt("i:h", array());

if (isset($args['h'])) {
    printHelp();
}

// Give the user an opportunity to provide an id
if (!isset($args['i'])) {
    $args['i'] = readline('A google insert id is required.  Enter an ID and press enter:');
}

// Make sure we have an id
if (empty($args['i'])) {
    echo "\n\n!!ERROR: A Google Insert ID is required\n\n";
    printHelp();
}

// Create the query
$log     = new LoggingClient(['projectId' => 'unified-logging-292316']);
$entries = $log->entries([
    'resourceName' => "projects/unified-logging-292316",
    'filter' => 'logName="projects/unified-logging-292316/logs/pharmacy-automation"
                 insertId="' . $args['i'] . '"',
    'orderBy' => 'timestamp desc',
    'pageSize' => 1,
    'resultLimit' => 1
]);

// Get the entry
if (!($entry = $entries->current())) {
    echo "\n\n!!ERROR: no entry for for id {$args['i']}\n";
    exit;
}

// See if the function is callable

// Get the payload
$payload  = $entry->info()['jsonPayload'];
$function = $payload['function'];
$context  = $payload['context'];
$changes  = ['created' => [], 'updated' => [], 'deleted' => []];

// Make sure we can access the function
if (!is_callable($function)) {
    echo "\n\n!!ERROR: {$function} is not callable in current scope\n\n";
    exit;
}

// Build the list of changes
foreach ($changes as $change_type => $empty_array) {
    if (isset($context[$change_type])) {
        // if the array is associative, we need to put it into a sequential array
        if (
            count(
                array_filter(
                    array_keys($context[$change_type]),
                    'is_string'
                )
            ) > 0
        ) {
            $changes[$change_type][] = $context[$change_type];
        } else {
            $changes[$change_type] = $context[$change_type];
        }
        break;
    }
}

printf(
    "We are going to call %s with %s created, %s deleted, and %s updated items.\n",
    $function,
    count($changes['created']),
    count($changes['deleted']),
    count($changes['updated'])
);
if (strtolower(readline('If this is correct, enter Y to continue:')) == 'y') {
    call_user_func($function, $changes);
} else {
    echo "!!USER ABORTED!!";
}

// call the function with the data
// bask in the glow
