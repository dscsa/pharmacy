<?php

ini_set('include_path', '/goodpill/webform');
require_once 'vendor/autoload.php';
require_once 'helpers/helper_appsscripts.php';
require_once 'helpers/helper_log.php';
require_once 'keys.php';

use GoodPill\AWS\SQS\{
    GoogleAppRequest\BaseRequest,
    GoogleAppRequest\HelperRequest,
    GoogleAppRequest\MergeRequest,
    GoogleAppRequest\Calendar\Create,
    GoogleCalendarQueue
};

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

/* Logic to give us a way to figure out if we should quit working */
// $stopRequested = false;
//
// pcntl_signal(
//     SIGTERM,
//     function ($signo, $signinfo) {
//         global $stopRequested, $log;
//         $stopRequested = true;
//         CliLog::warning("SIGTERM caught");
//     }
// );

// Grab and item out of the queue
$gdq = new GoogleCalendarQueue();

$executions = (ENVIRONMENT == 'PRODUCTION') ? 10000 : 2;

// Only loop so many times before we restart the script
for ($l = 0; $l < $executions; $l++) {
    $results  = $gdq->receive(['MaxNumberOfMessages' => 2]);
    $messages = $results->get('Messages');
    $complete = [];
    $loop_start = time();

    // An array of messages that have
    // been proccessed and can be deleted
    // If we've got something to work with, go for it
    if (is_array($messages) && count($messages) > 0) {
        $log_message = sprintf(
            "[%s] Processing %s messages\n",
            date('Y-m-d h:m:s'),
            count($messages)
        );

        GPLog::debug($log_message);
        CliLog::debug($log_message);

        foreach ($messages as $message) {
            $request = BaseRequest::factory($message);
            $log_message = sprintf(
                "[%s] New request type: %s for file %s - ",
                date('Y-m-d h:m:s'),
                $request->type,
                $request->fileId
            );

            if (isset($request->execution_id)) {
                GPLog::$exec_id = $request->execution_id;
            }

            if (isset($request->subroutine_id)) {
                GPLog::$subroutine_id = $request->subroutine_id;
            }

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
                GPLog::debug($log_message, $request->toArray());
                CliLog::notice($log_message);
                if ($request instanceof Create) {
                    sleep(10);
                }
            } else {
                $log_message .= "FAILED - Message: {$response->error}";
                GPLog::debug($log_message, $request->toArray());
                CliLog::notice($log_message);
                // When we get a failed message, we are going to wait 60
                // seconds before we try it again
                if (strpos(strtolower($response->error), 'please try again later') !== false) {
                    CliLog::notice("We failed and we are going to wait 15 minutes.  Then we will grab messages and try again");
                    sleep(60 * 15);
                    break;
                }
            }

            // /* Check to see if we've requeted to stop */
            // pcntl_signal_dispatch();
            //
            // if ($stopRequested) {
            //     CLiLog::warning('Finishing current Message then terminating');
            //     break;
            // }
        }
    }

    // Delete any complet messages
    if (!empty($complete)) {
        $log_message = sprintf(
            "[%s] Deleting %s messages",
            date('Y-m-d h:m:s'),
            count($complete)
        );

        GPLog::debug($log_message);
        CliLog::notice($log_message);

        $gdq->deleteBatch($complete);
    }

    unset($response);
    unset($messages);
    unset($complete);

    // we want to do 2 reqeusts every 30 seconds.  If it's taken less than 60 seconds
    // to complete these 5 mesages, we want to sleep until it's been 60 seconds
    $elapsed = time() - $loop_start;
    $sleep_time = 30 - $elapsed;

    if ($sleep_time > 0) {
        CliLog::notice("Waiting {$sleep_time} seconds before we grab more items");
        sleep($sleep_time);
    }

    // if ($stopRequested) {
    //     CLiLog::warning('Terminating execution from SIGTERM request');
    //     exit;
    // }
}
