<?php
require_once 'header.php';
require_once 'helpers/helper_sqs.php';
require_once 'testing/helpers.php';

use GoodPill\AWS\SQS\{
    PharmacySyncQueue,
    PharmacySyncRequest,
    PharmacyPatientQueue,
};

$changes_to_drugs          = getMock('data-update-drugs');
$changes_to_stock_by_month = getMock('data-update-stock-by-month');
$changes_to_patients_cp    = getMock('data-update-patients-cp');
$changes_to_patients_wc    = getMock('data-update-patients-wc');
$changes_to_rxs_single     = getMock('data-update-rxs-single');
$changes_to_orders_cp      = getMock('data-update-orders-cp');
$changes_to_orders_wc      = getMock('data-update-orders-wc');
$changes_to_order_items    = getMock('data-update-order-items');

try {
  $NOW = time();
  $exec_id = "pushToRequests-$NOW";
    $changes_sqs_messages = [];

    // Drugs
    if ($has_changes = get_sync_request('drugs', ['created'], $changes_to_drugs, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue drugs.created \n";
    }

    if ($has_changes = get_sync_request('drugs', ['deleted'], $changes_to_drugs, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue drugs.deleted \n";
    }

    if ($has_changes = get_sync_request('stock_by_month', ['created', 'deleted', 'updated'], $changes_to_stock_by_month, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue stock_by_month.all \n";
    }

    if ($has_changes = get_sync_request('patients_cp', ['updated'], $changes_to_patients_cp, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue patients_cp.updated \n";
    }

    if ($has_changes = get_sync_request('patients_wc', ['created'], $changes_to_patients_wc, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue patients_wc.created \n";
    }
    if ($has_changes = get_sync_request('patients_wc', ['deleted'], $changes_to_patients_wc, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue patients_wc.deleted \n";
    }
    if ($has_changes = get_sync_request('patients_wc', ['updated'], $changes_to_patients_wc, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue patients_wc.updated \n";
    }



    if ($has_changes = get_sync_request('rxs_single', ['created', 'updated'], $changes_to_rxs_single, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue rxs_single.create/updated \n";
    }
    if ($has_changes = get_sync_request('rxs_single', ['deleted'], $changes_to_rxs_single, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue rxs_single.deleted \n";
    }


    if ($has_changes = get_sync_request('orders_cp', ['created'], $changes_to_orders_cp, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue orders_cp.created \n";
    }
    if ($has_changes = get_sync_request('orders_cp', ['deleted'], $changes_to_orders_cp, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue orders_cp.deleted \n";
    }
    if ($has_changes = get_sync_request('orders_cp', ['updated'], $changes_to_orders_cp, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue orders_cp.updated \n";
    }


    // Orders WC
    if ($has_changes = get_sync_request('orders_wc', ['created'], $changes_to_orders_wc, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue orders_wc.created \n";
    }
    if ($has_changes = get_sync_request('orders_wc', ['deleted'], $changes_to_orders_wc, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue orders_wc.deleted \n";
    }
    if ($has_changes = get_sync_request('orders_wc', ['updated'], $changes_to_orders_wc, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue orders_wc.updated \n";
    }

    // Orders WC
    if ($has_changes = get_sync_request('order_items', ['created'], $changes_to_order_items, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue order_items.created \n";
    }

    if ($has_changes = get_sync_request('order_items', ['deleted'], $changes_to_order_items, $exec_id)) {
        //$changes_sqs_messages[] = $has_changes;
    } else {
        echo "Nothing to Queue order_items.deleted \n";
    }

    if ($has_changes = get_sync_request('order_items', ['updated'], $changes_to_order_items, $exec_id)) {
        $changes_sqs_messages[] = $has_changes;
        echo "Nothing to Queue order_items.updated \n";
    }
    echo "Sending ".count($changes_sqs_messages)." messages to the queue \n";
    if (count($changes_sqs_messages) > 0) {
        $changeq = new PharmacySyncQueue();
        $changeq->sendBatch($changes_sqs_messages);
    } else {
        print "No changes to send to the queue \n";
    }
} catch (\Exception $e) {
    $message = "PHP Uncaught Exception ";
    $message .= $e->getCode() . " " . $e->getMessage() ." ";
    $message .= $e->getFile() . ":" . $e->getLine() . "\n";
    $message .= $e->getTraceAsString();
    echo "$message";
}

try {
  $syncq = new PharmacySyncQueue();
  $patientQueue = new PharmacyPatientQueue();

  $results  = $syncq->receive([
    'WaitTimeSeconds' => 10,
  ]);
  $messages = $results->get('Messages');

  //  Iterate through all the messages in the queue and print them
  //  After it's been printed, immediately delete the message
  foreach($messages as $message) {
    $request = new PharmacySyncRequest($message);
    $syncq->updateTimeout($request);

    switch ($request->changes_to) {
        case 'drugs':
            print "Drugs Case \n";
            break;
        case 'stock_by_month':
            print "Stock by Month Case \n";
            break;
        default:
          print "Default Case \n";
          foreach (array_keys($request->changes) as $change_type) {
            foreach($request->changes[$change_type] as $changes) {
                $new_request = get_sync_request_single($request->changes_to, $change_type, $changes, 'PASS_TO_NEXT_QUEUE');
                $patientQueueBatch[] = $new_request;
            }
          }
          //    Push to next Queue
          //$patientQueue->send($newRequest);
          break;
    }

    $syncq->delete($request);
    echo "Request: $request->execution_id - $request->changes_to was removed from the queue \n";
  }
  echo "Sending Batch To Patient Queue \n";


  //print_r($patientQueueBatch);
  $patientQueue->sendBatch($patientQueueBatch);
  //$syncq->deleteBatch($complete);
} catch (\Exception $e) {
  $message = "PHP Uncaught Exception ";
  $message .= $e->getCode() . " " . $e->getMessage() ." ";
  $message .= $e->getFile() . ":" . $e->getLine() . "\n";
  $message .= $e->getTraceAsString();
  echo "$message";
}
