<?php

use Goodpill\Models\GpPatient;

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
php patient_match.php -p 123
    -c            the cp patient id to update to
    -w            the wc patient id to update

EOF;
    exit;
}

$args   = getopt("c:w:h", array());

if (!$args['c'] || !$args['w']) {
    echo "You must enter a carepoint and woo commerce id\n";
    return;
}
if (isset($args['h'])) {
    printHelp();
}
echo "Start Patient Update \n";

$results = force_match($args['c'], $args['w']);

echo "Start Patient Update \n";
print_r($results);


