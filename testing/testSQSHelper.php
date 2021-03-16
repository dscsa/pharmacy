<?php
/** Script to simulate a request going through to the new sqs helper **/

require_once 'header.php';
require_once 'helpers/helper_sqs.php';
require_once 'testing/helpers.php';

$changes_to_rxs_single     = getMock('data-update-rxs-single');
$changes_to_orders_wc      = getMock('data-update-orders-wc');

if ($has_changes = get_sync_request('rxs_single', ['created', 'updated'], $changes_to_rxs_single, $exec_id)) {
    //$changes_sqs_messages[] = $has_changes;
} else {
    echo "Nothing to Queue rxs_single.create/updated \n";
}
if ($has_changes = get_sync_request('rxs_single', ['deleted'], $changes_to_rxs_single, $exec_id)) {
    //$changes_sqs_messages[] = $has_changes;
} else {
    echo "Nothing to Queue rxs_single.deleted \n";
}


// Orders WC
if ($has_changes = get_sync_request('orders_wc', ['created'], $changes_to_orders_wc, $exec_id)) {
    $changes_sqs_messages[] = $has_changes;
} else {
    echo "Nothing to Queue orders_wc.created \n";
}
if ($has_changes = get_sync_request('orders_wc', ['deleted'], $changes_to_orders_wc, $exec_id)) {
    $changes_sqs_messages[] = $has_changes;
} else {
    echo "Nothing to Queue orders_wc.deleted \n";
}
if ($has_changes = get_sync_request('orders_wc', ['updated'], $changes_to_orders_wc, $exec_id)) {
    $changes_sqs_messages[] = $has_changes;
} else {
    echo "Nothing to Queue orders_wc.updated \n";
}

/************************/

foreach($changes_sqs_messages as $request) {
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
              $new_request = get_sync_request_single($request->changes_to, [$change_type], $changes, 'PASS_TO_NEXT_QUEUE');

              print_r($new_request->changes);
          }
        }
        break;
  }
}
