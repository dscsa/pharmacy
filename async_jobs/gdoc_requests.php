<?php

ini_set('include_path', '/goodpill/webform');
require_once 'vendor/autoload.php';
require_once 'helpers/helper_appsscripts.php';
require_once 'helpers/helper_log.php';
require_once 'keys.php';

use Sirum\AWS\SQS\GoogleAppRequest\BaseRequest;
use Sirum\AWS\SQS\GoogleAppRequest\HelperRequest;
use Sirum\AWS\SQS\GoogleAppQueue;

// Grab and item out of the queue
$gdq = new GoogleAppQueue();

$executions = (ENVIRONMENT == 'PRODUCTION') ? 10000 : 2;

// Only loop so many times before we restart the script
for ($l = 0; $l < $executions; $l++) {
    $results  = $gdq->receive(['MaxNumberOfMessages' => 10]);
    $messages = $results->get('Messages');
    $complete = [];

    // An array of messages that have
    // been proccessed and can be deleted
    // If we've got something to work with, go for it
    if (is_array($messages) && count($messages) > 0) {
        echo "Processing " . count($messages) . "messages\n";
        foreach ($messages as $message) {
            $request = BaseRequest::factory($message);

            printf(
                "[%s] New request type: %s for file %s - ",
                date('Y-m-d h:m:s'),
                $request->type,
                $request->fileId
            );

            // Figure out the type of message
            if ($request instanceof HelperRequest) {
                $url = GD_HELPER_URL;
            } elseif ($request instanceof MergeRequest) {
                $url = GD_MERGE_URL;
            }

            $response = json_decode(gdoc_post($url, $request->toArray()));

            if (@$response->results == 'success') {
                echo "Success!\n";
                $complete[] = $request;
            } else {
                echo "FAILED\n";
                echo "Message: {$request->error}\n";
            }
        }
    }

    // Delete any complet messages
    if (!empty($complete)) {
        $gdq->deleteBatch($complete);
    }

    unset($response);
    unset($messages);
    unset($complete);
}
