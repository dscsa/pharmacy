<?php

use \Sirum\DataModels\GoodPillOrder;
use \Sirum\Logging\SirumLog;

function v2_unpend_order_by_invoice(int $invoice_number) : bool
{
    while ($pend_group = find_order_pend_group($invoice_number)) { // Keep doing until we can't find a pended item
        $loop_count = (isset($loop_count) ? ++$loop_count : 1);
        if ($results = v2_fetch("/account/8889875187/pend/{$pend_group}", 'DELETE')) {
            SirumLog::info(
                "succesfully unpended all items from {$pend_group}",
                ['invoice_number' => $invoice_number]
            );
            return true;
        }

        if ($loop_count >= 5) {
            return false;
        }
    }

    return false;
}

/**
 * Check to see if this specific item has already been pended
 * @param  array   $item           The data for an order item
 * @param  boolean $include_picked (Optional) Should we check for pended and picked
 * @return boolean       False if the item is not pended in v2
 */
function find_order_pend_group(int $invoice_number) : ?string
{

    $order = new GoodPillOrder(['invoice_number' => $invoice_number]);

    $order_based = [
        'invoice_number'   => $order->invoice_number,
        'order_date_added' => $order->order_date_added
    ];

    $patient_based = [
        'invoice_number'   => $order->invoice_number,
        'patient_date_added' => $order->patient->patient_date_added
    ];

    $possible_pend_groups = [
        'refill'          => pend_group_refill($order_based),
        'webform'         => pend_group_webform($order_based),
        'new_patient'     => pend_group_new_patient($patient_based),
        'new_patient_old' => pend_group_new_patient_old($patient_based),
        'manual'          => pend_group_manual($order_based)
    ];

    foreach ($possible_pend_groups as $type => $group) {
        $pend_url = "/account/8889875187/pend/{$group}";
        $results  = v2_fetch($pend_url, 'GET');
        if (
            !empty($results)
            && @$results[0]['next'][0]['pended']
        ) {
            return $group;
        }
    }

    return null;
}
