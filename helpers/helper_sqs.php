<?php

use GoodPill\AWS\SQS\PharmacySyncRequest;
use GoodPill\Models\GpPatient;
use GoodPill\Models\GpOrder;
use GoodPill\Logging\GPLog;

require_once 'helpers/helper_laravel.php';

/**
 * Get a Pharmach sync request for queueing
 * @param  string $changes_to   [description]
 * @param  array  $change_types [description]
 * @param  array  $changes      [description]
 * @return [type]               [description]
 */
function get_sync_request(
    string $changes_to,
    array $change_types,
    array $changes,
    string $execution = null
) : ?PharmacySyncRequest {
    $included_changes = array_intersect_key(
        $changes,
        array_flip($change_types)
    );

    // If we don't have any data,
    // there's no reason to actually queue it up
    if (array_sum(array_map("count", $included_changes)) == 0) {
        return null;
    }

    $syncing_request             = new PharmacySyncRequest();
    $syncing_request->changes_to = $changes_to;
    $syncing_request->changes    = $included_changes;
    $syncing_request->group_id   = 'linear-sync';
    $syncing_request->execution_id = $execution;
    return $syncing_request;
}

function get_sync_request_single(
    string $changes_to,
    string $change_type,
    array $changes,
    string $execution = null
) : ?PharmacySyncRequest {

    switch ($changes_to) {
        case 'patients_cp':
        case 'patients_wc':
            $group_id = $changes['first_name']."_".$changes['last_name']."_".$changes['birth_date'];
            break;
        case 'rxs_single':
        case 'orders_cp':
        case 'orders_wc':
        case 'order_items':
            if (isset($changes['patient_id_cp'])) {
                $patient = GpPatient::where('patient_id_cp', '=', $changes['patient_id_cp'])->first();
            } elseif (isset($changes['patient_id_wc'])) {
                $patient = GpPatient::where('patient_id_wc', '=', $changes['patient_id_wc'])->first();
            } elseif (isset($changes['invoice_number'])) {
                $order = GpOrder::where('invoice_number', '=', $changes['invoice_number'])->first();
                $patient = $order->getPatient();
            }

            if (isset($patient) && $patient->exists) {
                $group_id = $patient->first_name.'_'.$patient->last_name.'_'.$patient->birth_date;
            } else {
                GPLog::warning(
                    "Problem finding a patient group id",
                    [
                        'changes' => $changes,
                        'changes_to' => $changes_to,
                        'change_type' => $change_type,
                     ]
                );
                $group_id = 'UNKNOWN_GROUP_ID';
            }
            break;
        default:
            break;
    }

    if (!isset($group_id)) {
        $group_id = 'UNKNOWN_GROUP_ID';
    }

    GPLog::debug(
        "Creating Patient Sync Request for group ID",
        [
            'group_id' => $group_id,
         ]
    );

    $syncing_request               = new PharmacySyncRequest();
    $syncing_request->changes_to   = $changes_to;
    $syncing_request->changes      = [$change_type => [$changes]];
    $syncing_request->group_id     = sha1($group_id);
    $syncing_request->patient_id   = $group_id;
    $syncing_request->execution_id = $execution;
    return $syncing_request;
}
