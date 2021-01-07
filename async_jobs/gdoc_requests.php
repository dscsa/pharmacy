<?php

ini_set('include_path', '/goodpill/webform');
require_once 'vendor/autoload.php';
require_once 'keys.php';

use Sirum\AWS\SQS\GoogleDocsRequests\BaseRequest;
use Sirum\AWS\SQS\GoogleDocsQueue;

// Grab and item out of the queue
$gdq = new GoogleDocsQueue();

// Only loop so many times before we restart the script
for ($l = 0; $l < 10000; $l++) {
    $results  = $gdq->receive(['MaxNumberOfMessages' => 10]);
    $messages = $results->get('Messages');

    // An array of messages that have
    // been proccessed and can be deleted
    $complete = array();

    // If we've got something to work with, go for it
    if (is_array($messages) && count($messages) > 0) {
        foreach ($messages as $message) {
            // Figure out the type of message
            // Use the correct endpoint
            // Call the URL
            print_r($message);
        }
    }

    unset($results);
    unset($messages);
}
