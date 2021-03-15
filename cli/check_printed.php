<?php

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';
require_once 'helpers/helper_pagerduty.php';
require_once 'helpers/helper_appsscripts.php';
require_once 'keys.php';

$args   = getopt("s:e:hnqvr", array());

function printHelp()
{
    echo <<<EOF
php check_invoices.php [-h -n] -s '-1 Hour' -e '-30 Minutes'
    -e end_date   Any date expression that can be parsed by string to
                  time.  Defaults to '-30 Minutes' This time must be greater
                  than the start_date.
    -h            Print this message
    -n            Don't alert pager duty
    -s start_date Any date expression that can be parsed by string to
                  time.  Defaults to '-1 Hour'
    -q            Print failed invoices as comma seperated list with no failure details
    -v            Output success and failures.  Will automatically trigger -n option
    -r            Trigger a reprint

EOF;
    exit;
}

if (isset($args['h'])) {
    printHelp();
}

$start_str = '-1 Hour';
$end_str = '-30 Minutes';

if (isset($args['s'])) {
    $start_str = $args['s'];
}

if (isset($args['e'])) {
    $end_str = $args['e'];
}

$start = date('c', strtotime($start_str));
$end   = date('c', strtotime($end_str));
$incident_id = "invoice_printing_failed:{$start}-{$end}";
if ($start > $end) {
    echo "The end date must be greater than the start date\n";
    printHelp();
}

echo "Checking dispensed invoices between {$start} and {$end}\n";

$mysql = GoodPill\Storage\Goodpill::getConnection();
$pdo   = $mysql->prepare(
    "SELECT invoice_number, order_date_dispensed, invoice_doc_id
        FROM gp_orders
        WHERE order_date_dispensed BETWEEN :oldest AND :newest
        ORDER BY invoice_number ASC;"
);

$pdo->bindParam(':oldest', $start, \PDO::PARAM_STR);
$pdo->bindParam(':newest', $end, \PDO::PARAM_STR);
$pdo->execute();

$printed = [];
$failed  = [];

while ($invoice = $pdo->fetch()) {
    if ($invoice['invoice_doc_id']) {
        $results  = gdoc_details($invoice['invoice_doc_id']);
        if (isset($results->parent) && $results->parent->name != 'Printed') {
            $failed[$invoice['invoice_number']] = [
                'dispensed' => $invoice['order_date_dispensed'],
                'trashed' => (bool) $results->trashed
            ];
            printf(
                "Invoice %s FAILED to move to print.  It was dispensed on %s and isTrashed = %s\n",
                $invoice['invoice_number'],
                $invoice['order_date_dispensed'],
                (bool) $results->trashed
            );
        } elseif (isset($args['v'])) {
            $printed[] = $invoice['invoice_number'];
            printf(
                "Invoice %s printed Successfully.\n",
                $invoice['invoice_number']
            );
        }
    } else {
        $failed[$invoice['invoice_number']] = [
            'dispensed' => $invoice['order_date_dispensed'],
            'doc_id' => (bool) $invoice['invoice_doc_id']
        ];
        printf(
            "Invoice %s FAILED was dispensed %s but never created or the database wasn't updated\n",
            $invoice['invoice_number'],
            $invoice['order_date_dispensed']
        );
    }
}

if (!isset($args['n']) && count($failed) > 0) {
    pd_low_priority("Invoices failed to print", $incident_id, $failed);
}

if (isset($args['q'])) {
    echo implode(',', array_keys($failed));
}

if (isset($args['r'])) {
    echo "Reprinting failed invoices: ";
    $command = "php invoice_tool.php -up -m " . implode(',', array_keys($failed));
    echo shell_exec($command);
}
