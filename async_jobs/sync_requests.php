<?php

ini_set('include_path', '/goodpill/webform');
require_once 'vendor/autoload.php';
require_once 'helpers/helper_appsscripts.php';
require_once 'helpers/helper_log.php';
require_once 'keys.php';

use GoodPill\AWS\SQS\{
    PharmacySyncQueue,
    PharmacySyncRequest
};

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

// Grab and item out of the queue
$syncq = new PharmacySyncQueue();

$executions = (ENVIRONMENT == 'PRODUCTION') ? 10000 : 20;

// Only loop so many times before we restart the script
for ($l = 0; $l < $executions; $l++) {
    if (file_exists('/tmp/block-sync.txt')) {
        sleep(30);
        CliLog::error('Sync Job blocked by /tmp/block-sync.txt');
        contine;
    }

    $results  = $syncq->receive(['MaxNumberOfMessages' => 1]);
    $messages = $results->get('Messages');
    $complete = [];

    // An array of messages that have
    // been proccessed and can be deleted
    // If we've got something to work with, go for it
    if (is_array($messages) && count($messages) > 0) {
        $log_message = sprintf(
            "Processing %s messages\n",
            count($messages)
        );

        GPLog::debug($log_message);
        CliLog::debug($log_message);
        foreach ($messages as $message) {
            $request = new PharmacySyncRequest($message);
            $changes = $request->changes;

            $log_message = sprintf(
                "New sync for %s changes to %s",
                implode(',', array_keys($changes)),
                $request->changes_to
            );

            GPLog::debug($log_message);
            CliLog::notice($log_message);
            try {
                switch ($request->changes_to) {
                    case 'drugs':
                        update_drugs($changes);
                        break;
                    case 'stock_by_month':
                        update_stock_by_month($changes);
                        break;
                    case 'patients_cp':
                        update_patients_cp($changes);
                        break;
                    case 'patients_wc':
                        update_patients_wc($changes);
                        break;
                    case 'rxs_single':
                        update_rxs_single($changes);
                        break;
                    case 'orders_cp':
                        update_orders_cp($changes);
                        break;
                    case 'orders_wc':
                        update_orders_wc($changes);
                        break;
                    case 'order_items':
                        update_order_items($changes);
                        break;
                }
            } catch (\Exception $e) {
                // Log the error
                $message = "SYNC JOB - ERROR ";
                $message .= $e->getCode() . " " . $e->getMessage() ." ";
                $message .= $e->getFile() . ":" . $e->getLine() . "\n";
                $message .= $e->getTraceAsString();

                GPLog::alert($message);

                // Create the block file
                file_put_contents('/tmp/block-sync.txt', date('c'));

                break;
            }

            $complete[] = $request;
        }
    }

    // Delete any complet messages
    if (!empty($complete)) {
        $log_message = sprintf(
            "Deleting %s messages",
            count($complete)
        );

        GPLog::debug("Deleting %s messages");
        CliLog::notice("Deleting %s messages");

        $syncq->deleteBatch($complete);
    }

    unset($changes);
    unset($response);
    unset($messages);
    unset($complete);
}
