<?php

use GoodPill\Models\GpOrder;
use GoodPill\Models\GpOrderItem;
use GoodPill\Models\GpRxsGrouped;
use GoodPill\Models\GpRxsSingle;

/**
 * This is only for helper functions specifically using models
 * Ideally, every single method will take a model or item as input
 * and return something as the output to be used
 *
 * No data should be set on a model from a function
 */

/**

Scaffolding test code to simulate load_full_order and passing in data
echo "------------ \n";
$order->items->each(function($item) {
echo "$item->rx_number \n";
});

echo "============= \n";

$grouped->each(function($item) use ($order) {
//get_days_and_message($item, $order);
});
 */




function get_days_and_message($item, GpOrder $order) {
    // @TODO move to instances
    //  Right now we need to check $item for many conditions and then
    //  instanciate a model based off the different criteria

    echo "$item->drug_name - $item->rx_number from top \n";
    if ($item instanceof GpOrderItem) {
        $is_order = true;
        $is_item = false;
        $is_patient = false;

        $is_added_manually = $item->isAddedManually();
        $is_webform = $item->isWebform();
        $is_syncable = false;

    }

    if ($item instanceof GpRxsGrouped) {
        $is_order = true;
        $is_item = false;
        $is_patient = false;

        $is_not_offered = $item->isNotOffered();
        $is_in_order = $item->isInOrder($order);
        $is_added_manually = false;
        $is_webform = false;
        $is_syncable = !$is_in_order;
    }


    $is_no_transfer = $item->isNoTransfer();
    //  Need to revisit isRefill
    $is_refill = $item->isRefill($order);

    $is_refill_only = $item->isRefillOnly();
    $days_left_before_expiration = $item->getDaysLeftBeforeExpiration();
    $days_left_in_refills = $item->getDaysLeftInRefills();
    $days_left_in_stock = $item->getDaysLeftInStock();
    $days_default = $item->days_default();

    $is_not_rx_parsed = $item->isNotRxParsed();

    // These are now all computed accessors on the GpRxsGrouped Entity
    /*
    $date_added = @$item['order_date_added'] ?: $item['rx_date_written'];
    $days_early_next = strtotime($item['refill_date_next']) - strtotime($date_added);
    $days_early_default = strtotime($item['refill_date_default']) - strtotime($date_added);
    $days_since = strtotime($date_added) - strtotime($item['refill_date_last']);
    */

    /*
  There was some error parsint the Rx
 */

    if ($is_not_rx_parsed) {
        //  Throw a an error log
        //log_error("helper_days_and_message: RX WAS NEVER PARSED", $item);
    }

    /*
      We have multiple occurances of drugs on the same order
     */
    if (@$item['item_date_added'] and $is_duplicate_gsn) {
        log_error("helper_days_and_message: $item[drug_generic] is duplicate GSN.  Likely Mistake. Different sig_qty_per_day?", ['item' => $item, 'order' => $patient_or_order]);
    }


    return true;
}