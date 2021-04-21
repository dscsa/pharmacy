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

error_reporting(E_ERROR);

function special_match($patient_id_cp, $patient_id_wc, $force_match = false) {
    $patient_match = is_patient_matched_in_wc($patient_id_cp);
    print_r($patient_match);

    if ($patient_match && @$patient_match['patient_id_wc'] != $patient_id_wc) {
        if ($force_match) {
            echo "We must force \n";
            $patient = GpPatient::find($patient_id_cp);
            $old_patient = GpPatient::find($patient_match['$patient_id_cp']);

            echo "$patient->last_name \n";
            echo "$old_patient->last_name \n";
        }
    }
}


function printHelp() {
    echo <<<EOF
php patient_match.php -p 123
    -c            the cp patient id to update to
    -w            the wc patient id to update

EOF;
    exit;
}

$args   = getopt("c:w:h", array());

if (!args['c'] || !$args['w']) {
    echo "You must enter a carepoint and woo commerce id\n";
    return;
}
if (isset($args['h'])) {
    printHelp();
}
echo "Start Patient Update \n";

//special_match($args['c'], $args['w'], true);

$data = is_patient_matched_in_wc($args['c']);
print_r($data);
echo "patient_id_cp:{$args['c']}, patient_id_wc:{$args['w']} was successfully updated \n";


