<?php

use GoodPill\Logging\CliLog;

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';

require_once 'exports/export_gd_orders.php';
require_once 'exports/export_v2_order_items.php';
require_once 'dbs/mysql_wc.php';
require_once 'dbs/mssql_cp.php';
require_once 'helpers/helper_log.php';
require_once 'helpers/helper_constants.php';
require_once 'helpers/helper_full_patient.php';
require_once 'helpers/helper_matching.php';
require_once 'keys.php';


function printHelp() {
    echo <<<EOF
php patient_match.php -c 123 -w 40
    -c            the cp patient id to update to
    -w            the wc patient id to update
    -f            force this match and clear out any meta keys and inactivate users

EOF;
    exit;
}

$args   = getopt("c:w:fh", array());

if (!$args['c'] || !$args['w']) {
    echo "You must enter a carepoint and woo commerce id\n";
    return;
}
if (isset($args['h'])) {
    printHelp();
}
CliLog::notice("Start Patient Update with cp_id: {$args['c']} && wc_id: {$args['w']}");
$force = isset($args['f']);

match_patient($args['c'], $args['w'], $force);
CliLog::notice('Patient has been force matched');



