<?php

ini_set('include_path', '/goodpill/webform');
require_once 'vendor/autoload.php';
require_once 'helpers/helper_appsscripts.php';
require_once 'helpers/helper_log.php';
require_once 'keys.php';

use Sirum\AWS\SQS\{
    GoogleAppRequest\BaseRequest,
    GoogleAppRequest\HelperRequest,
    GoogleAppQueue
};

use Sirum\Logging\SirumLog;

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

        $log_message = sprintf(
            "[%s] Processing %s messages\n",
            date('Y-m-d h:m:s'),
            count($messages)
        );

        SirumLog::debug($log_message);
        echo $log_message;

        foreach ($messages as $message) {
            $request = BaseRequest::factory($message);
            $log_message = sprintf(
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
                $log_message .= "Success!";
                $complete[] = $request;
            } else {
                $log_message .= "FAILED - Message: {$request->error}";
            }

            SirumLog::debug($log_message);
            echo $log_message . "\n";
        }
    }

    // Delete any complet messages
    if (!empty($complete)) {
        $log_message = sprintf(
            "[%s] Deleting %s messages",
            date('Y-m-d h:m:s'),
            count($complete)
        );

        SirumLog::debug($log_message);
        echo $log_message . "\n";

        $gdq->deleteBatch($complete);
    }

    unset($response);
    unset($messages);
    unset($complete);
}
