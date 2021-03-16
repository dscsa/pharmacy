<?php

use GoodPill\AWS\SQS\PharmacySyncRequest;
use GoodPill\DataModels\GoodPillPatient;

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
    array $change_types,
    array $changes,
    string $execution = null
) : ?PharmacySyncRequest {
    $patient = null;

    switch($changes_to) {
        case 'patients_cp':
        case 'patients_wc':
            $group_id = $changes['last_name']."_".$changes['first_name']."_".$changes['birth_date'];
            break;
        case 'rxs_single':
        case 'orders_cp':
        case 'orders_wc':
        case 'order_items':
            foreach (
                [
                    'patient_id_cp' => $changes['patient_id_cp'],
                    'patient_id_wc' => $changes['patient_id_wc'],
                 ] as $k => $v
             ) {
                if (isset($v)) {
                    $patient = new GoodPillPatient([$k => $v]);
                    break;
                }
            }

            if ($patient->isMatched()) {
                $group_id = $patient->last_name.'_'.$patient->first_name.'_'.$patient->birth_date;
            } else {
                throw new \Exception("Cannot find matching patient");
            }

            break;
        default:
            throw new \Exception("No change type detected");
            break;
    }

    $syncing_request             = new PharmacySyncRequest();
    $syncing_request->changes_to = $changes_to;
    $syncing_request->changes    = $changes;
    $syncing_request->group_id   = $group_id;
    $syncing_request->execution_id = $execution;
    return $syncing_request;
}
