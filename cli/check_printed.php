<?php

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';
require_once 'helpers/helper_pagerduty.php';
require_once 'keys.php';

$args   = getopt("s:e:h", array());

function printHelp()
{
    echo <<<EOF
    php check_invoices.php [-h] [-s '-1 Hour'] [-e '-30 Minutes']
        -s start_date Any date expression that can be parsed by string to
                      time.  Defaults to '-1 Hour'
        -e end_date   Any date expression that can be parsed by string to
                      time.  Defaults to '-30 Minutes' This time must be greater
                      than the start_date.
        -h            Print this message

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

$mysql = Sirum\Storage\Goodpill::getConnection();
$pdo   = $mysql->prepare(
    "SELECT invoice_number, order_date_dispensed, invoice_doc_id
		        FROM gp_orders
		        WHERE
		                order_date_dispensed
                        BETWEEN :oldest
                            AND :newest;"
);

$pdo->bindParam(':oldest', $start, \PDO::PARAM_STR);
$pdo->bindParam(':newest', $end, \PDO::PARAM_STR);
$pdo->execute();

$opts = [
    'http' => [
      'method'  => 'GET',
      'header'  => "Accept: application/json\r\n"
    ]
];
$context  = stream_context_create($opts);
$base_url = "https://script.google.com/macros/s/AKfycbwL2Ct6grT3cCgaw27GrUSzznur"
            . "9W9xhDgs-YoZvqeepZjWYjR9/exec?GD_KEY=Patients1st!";


while ($invoice = $pdo->fetch()) {
    if ($invoice['invoice_doc_id']) {
        $url      = $base_url . '&fileId=' . $invoice['invoice_doc_id'];
        $results  = json_decode(file_get_contents($url, false, $context));
        if ($results->parent->name != 'Printed') {
            // Should create an alert because the invoice should be printed
            $message = "Invoice {$invoice['invoice_number']} was dispensed at "
                       . "{$invoice['order_date_dispensed']} but hasn't been printed.  ";
            if ($results->trashed) {
                $message .= "It has been moved to the trash and not recreated.";
            }
        }
    } else {
        // Should create an alert because there should always be an invoice
        $message = "Invoice {$invoice['invoice_number']} was dispensed at "
                   . "{$invoice['order_date_dispensed']} but it doesn't have an "
                   . "invoice_doc_id.";
    }
    if (isset($message)) {
        pd_low_priority($message, $incident_id);
        echo $message . "\n";
        unset($message);
    }
}

echo "\n";
