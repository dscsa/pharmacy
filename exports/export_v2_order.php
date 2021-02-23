<?php

use \GoodPill\DataModels\GoodPillOrder;
use \GoodPill\Logging\GPLog;

function v2_unpend_order_by_invoice(int $invoice_number, ?array $pend_params = null) : bool
{

    GPLog::debug("Unpending entire order via V2 {$invoice_number}", ['invoice_number' => $invoice_number]);
    while ($pend_group = find_order_pend_group($invoice_number, $pend_params)) { // Keep doing until we can't find a pended item
        $loop_count = (isset($loop_count) ? ++$loop_count : 1);
        if ($results = v2_fetch("/account/8889875187/pend/{$pend_group}", 'DELETE')) {
            GPLog::info(
                "succesfully unpended all items from {$pend_group}",
                ['invoice_number' => $invoice_number]
            );
            return true;
        }

        if ($loop_count >= 5) {
            return false;
        }
    }
    GPLog::debug("No drugs pended under order #{$invoice_number}", ['invoice_number' => $invoice_number]);
    return false;
}

/**
 * Check to see if this specific item has already been pended
 * @param  array   $item           The data for an order item
 * @param  boolean $include_picked (Optional) Should we check for pended and picked
 * @return boolean       False if the item is not pended in v2
 */
function find_order_pend_group(int $invoice_number, ?array $pend_params = null) : ?string
{

    if (!is_null($pend_params)) {
        $order_based = [
            'invoice_number'   => $invoice_number,
            'order_date_added' => @$pend_params['order_date_added']
        ];

        $patient_based = [
            'invoice_number'     => $invoice_number,
            'patient_date_added' => @$pend_params['patient_date_added']
        ];
    } else {
        $order = new GoodPillOrder(['invoice_number' => $invoice_number]);
        $order_based = [
            'invoice_number'   => $order->invoice_number,
            'order_date_added' => $order->order_date_added
        ];

        $patient_based = [
            'invoice_number'   => $order->invoice_number,
            'patient_date_added' => $order->patient->patient_date_added
        ];
    }

    $possible_pend_groups = [
        'refill'          => pend_group_refill($order_based),
        'webform'         => pend_group_webform($order_based),
        'new_patient'     => pend_group_new_patient($patient_based),
        'new_patient_old' => pend_group_new_patient_old($patient_based),
        'manual'          => pend_group_manual($order_based)
    ];

    GPLog::debug(
        "Trying to find Pended Rx for #{$invoice_nuber}",
        [
            'invoice_number' => $invoice_number,
            'pend_groups'    => $possible_pend_groups
        ]
    );
    foreach ($possible_pend_groups as $type => $group) {
        $pend_url = "/account/8889875187/pend/{$group}";
        $results  = v2_fetch($pend_url, 'GET');
        if (
            !empty($results)
            && @$results[0]['next'][0]['pended']
        ) {
            // This order has already been picked, we need to quit trying
            if (@$results[0]['next'][0]['picked']) {
                GPLog::alert("We are trying to unpend a picked order: {$group}");
                return null;
            }
            GPLog::debug(
                "Pend Group with pended RX found for #{$invoice_nuber}",
                [
                    'invoice_number'   => $invoice_number,
                    'valid_pend_group' => $group
                ]
            );
            return $group;
        }
    }
    GPLog::debug(
        "NO Pend Group found with pended RX found for #{$invoice_nuber}",
        [
            'invoice_number'   => $invoice_number
        ]
    );
    return null;
}
