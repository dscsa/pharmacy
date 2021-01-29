<?php

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';

require_once 'exports/export_gd_orders.php';
require_once 'exports/export_v2_order_items.php';
require_once 'dbs/mysql_wc.php';
require_once 'dbs/mssql_cp.php';
require_once 'updates/update_orders_cp.php';
require_once 'helpers/helper_imports.php';
require_once 'helpers/helper_log.php';
require_once 'helpers/helper_constants.php';
require_once 'helpers/helper_full_item.php';
require_once 'helpers/helper_full_patient.php';
require_once 'helpers/helper_days_and_message.php';
require_once 'keys.php';

error_reporting(E_ERROR);

function printHelp() {
    echo <<<EOF
php invoice_tool.php -m 123,456,789 -o 999 [-d -h -p -u]
    -d            Just display the details without actually making the calls
    -h            Display this message
    -m invoices   seperated list of invoice numbers to update or print
    -o order_id   A single invoice you would like to update or print
    -p            move the invoice out of the pending folder and print
    -u            update the invoice with the current data

EOF;
    exit;
}

$args   = getopt("m:o:uhpd", array());

if (isset($args['h'])) {
   printHelp();
}

$mysql  = new Mysql_Wc();
$orders = [];

if (isset($args['m'])) {
    $orders = array_merge($orders, explode(',', $args['m']));
}

if (isset($args['o'])) {
    $orders[] = $args['o'];
}

if (count($orders) == 0) {
    echo "You must specify at least one invoice to modify\n";
    printHelp();
}

foreach ($orders as $orderNumber) {
    if (!isset($args['d'])) {
        $order = load_full_order(["invoice_number" => $orderNumber], $mysql);
    }

    if (isset($args['u'])) {
        if (!isset($args['d'])) {
            $order = export_gd_update_invoice($order, 're-print', $mysql, true);
        }
        echo "Invoice {$orderNumber} Updated\n";
    }

    if (isset($args['p'])) {
        if (!isset($args['d'])) {
            $order = export_gd_publish_invoice($order);
            export_gd_print_invoice($order[0]['invoice_number']);
        }
        echo "Invoice {$orderNumber} queued to print\n";
    }
}
