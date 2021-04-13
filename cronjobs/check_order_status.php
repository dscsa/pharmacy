<?php

ini_set('memory_limit', '1024M');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';
require_once 'keys.php';

use Carbon\Carbon;
use GoodPill\Models\GpOrder;

$three_days_ago = Carbon::now()->subDays(3)->toDateTimeString();

$shippedOrders = GpOrder::where('order_status', 'Shipped')
    ->whereNull('tracking_number')
    ->where('order_date_shipped', '<=', $three_days_ago)->get();

$dispensedOrders = GpOrder::where('order_status', 'Dispensed')
    ->whereNull('tracking_number')
    ->where('order_date_dispensed', '<=', $three_days_ago)->get();

print_r($shippedOrders);