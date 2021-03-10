<?php

use GoodPill\AWS\SQS\PharmacySyncRequest;

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
