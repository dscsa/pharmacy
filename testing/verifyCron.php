<?php

require_once 'header.php';
require_once 'helpers/helper_constants.php';
require_once 'testing/helpers.php';
require_once 'helpers/helper_laravel.php';

use Carbon\Carbon;
use GoodPill\Models\GpOrder;

$three_days_ago = Carbon::now()->subDays(3)->toDateTimeString();
$thiry_days_ago = Carbon::now()->subDays(30)->toDateTimeString();


echo "Three Days Ago: $three_days_ago";

$shippedOrders = GpOrder::where('order_status', 'Shipped')
    ->whereNull('tracking_number')
    ->where('order_date_shipped', '<=', $three_days_ago)->get();

$dispensedOrders = GpOrder::where('order_status', 'Dispensed')
    ->where('order_date_dispensed', '<=', $three_days_ago)
    ->where('order_date_dispensed', '>', $thiry_days_ago)
    ->get(['invoice_number']);

echo $dispensedOrders->count();

if ($shippedOrders->count() > 0) {
    $shippedSubject = 'Orders Missing Tracking Numbers';
    $shippedBody = 'The following orders are to be shipped but are missing tracking numbers after 3 days<br>';
    foreach ($shippedOrders as $order) {
        $shippedBody .= $order['invoice_number'].'<br>';
    }

    echo $shippedBody;
}

if ($dispensedOrders->count() > 0) {
    $dispensedSubject = 'Orders Need To Be Dispensed';
    $dispensedBody = 'The following orders are dispensed but have not been shipped after 3 days<br>';
    $dispensedContact = 'something???????';
    $dispensedassign_to = 'something???????';
    $dispensedDueDate = 'something??????????';
    foreach ($dispensedOrders as $order) {
        $dispensedBody .= $order['invoice_number'].'<br>';
    }
    echo $dispensedBody;

}


/*
$salesforce = [
    "subject"   => $subject,
    "body"      => "$body $created_date",
    "contact"   => "{$patient['first_name']} {$patient['last_name']} {$patient['birth_date']}",
    "assign_to" => $assign,
    "due_date"  => date('Y-m-d')
];

$event_title  = @$created['rx_number']." Missing GSN: {$salesforce['contact']} $created_date";

$message_as_string = implode('_', $salesforce);
$notification = new \GoodPill\Notifications\Salesforce(sha1($message_as_string), $message_as_string);

if (!$notification->isSent()) {
    GPLog::debug(
        $subject,
        [
            'created' => $created,
            'body'    => $body
        ]
    );

    create_event($event_title, [$salesforce]);
} else {
    GPLog::warning(
        "DUPLICATE Saleforce Message".$subject,
        [
            'created' => $created,
            'body'    => $body
        ]
    );
}

$notification->increment();
*/
