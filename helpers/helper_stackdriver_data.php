<?php

use Google\Cloud\Logging\LoggingClient;

function get_data_by_insert(string $insert_id)
{
    return get_data_from_log('logName="projects/unified-logging-292316/logs/pharmacy-automation"
                              insertId="' . $insert_id . '"');
}

function get_data_from_log(string $query)
{
    // Create the query
    $log     = new LoggingClient(['projectId' => 'unified-logging-292316']);
    $entries = $log->entries([
        'resourceName' => "projects/unified-logging-292316",
        'filter' => $query,
        'orderBy' => 'timestamp desc',
        'pageSize' => 1,
        'resultLimit' => 1
    ]);

    // Get the entry
    if (!($entry = $entries->current())) {
        echo "\n\n!!ERROR: no entry for for id {$args['i']}\n";
        return null;
    }

    $payload = $entry->info()['jsonPayload'];
    return $payload['context'];
}
