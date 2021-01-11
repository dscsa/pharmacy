<?php

require_once 'helpers/helper_appsscripts.php';

use Sirum\AWS\SQS\GoogleDocsRequests\Delete;
use Sirum\AWS\SQS\GoogleDocsQueue;
use Sirum\DataModels\Order;
use Sirum\Logging\SirumLog;

global $gd_merge_timers;

$gd_merge_timers = [
    'export_gd_update_invoice'  => 0,
    'export_gd_delete_invoice'  => 0,
    'export_gd_print_invoice'   => 0,
    'export_gd_publish_invoice' => 0
];


function export_gd_update_invoice($order, $reason, $mysql, $try2 = false)
{
    global $gd_merge_timers;
    $start = microtime(true);

    SirumLog::notice(
        'export_gd_update_invoice: called',
        [
              "invoice_number" => $order[0]['invoice_number'],
              "order"   => $order,
              "reason"  => $reason,
              "try2"    => $try2
        ]
    );

    if (! count($order)) {
        SirumLog::error(
            "export_gd_update_invoice: invoice error #2 of 2}",
            [
                "order"  => $order,
                "reason" => $reason
            ]
        );

        return $order;
    }

    export_gd_delete_invoice($order[0]['invoice_doc_id']); //Avoid having multiple versions of same invoice

    $args = [
        'method'   => 'mergeDoc',
        'template' => 'Invoice Template v1',
        'file'     => 'Invoice #'.$order[0]['invoice_number'],
        'folder'   => INVOICE_PENDING_FOLDER_NAME,
        'order'    => $order
    ];

    echo "\ncreating invoice ".$order[0]['invoice_number']." (".$order[0]['order_stage_cp'].")\n";

    $start = microtime(true);

    $result = gdoc_post(GD_MERGE_URL, $args);

    $time = ceil(microtime(true) - $start);

    echo " completed in $time seconds";

    $invoice_doc_id = json_decode($result, true);

    if ( ! $invoice_doc_id) {
        if (! $try2) {
            SirumLog::notice(
                "export_gd_update_invoice: invoice error #1 of 2}",
                [
                    "invoice_number" => $order[0]['invoice_number'],
                    "args"           => $args,
                    "results"        => $result,
                    "attempt"        => 1
                ]
            );

            return export_gd_update_invoice($order, $reason, $mysql, true);
        }

        SirumLog::notice(
            "export_gd_update_invoice: invoice error #2 of 2}",
            [
                "invoice_number" => $order[0]['invoice_number'],
                "args"           => $args,
                "results"        => $result,
                "attempt"        => 2
            ]
        );

        return $order;
    }

    if ($order[0]['invoice_doc_id']) {
        SirumLog::notice(
            "export_gd_update_invoice: UPDATED invoice for Order #{$order[0]['invoice_number']}",
            [
                "invoice_number"     => $order[0]['invoice_number'],
                "stage"              => $order[0]['order_stage_cp'],
                "new_invoice_doc_id" => $invoice_doc_id,
                "old_invoice_doc_id" => $order[0]['invoice_doc_id'],
                "reason"             => $reason,
                "time"               => $time
            ]
        );
    } else {
        SirumLog::notice(
            "export_gd_update_invoice: CREATED invoice for Order #{$order[0]['invoice_number']}",
            [
                "invoice_number" => $order[0]['invoice_number'],
                "stage"          => $order[0]['order_stage_cp'],
                "invoice_doc_id" => $invoice_doc_id,
                "reason"         => $reason,
                "time"           => $time
            ]
        );
    }

    //Need to make a second loop to now update the invoice number
    foreach ($order as $i => $item) {
        $order[$i]['invoice_doc_id'] = $invoice_doc_id;
    }

    $sql = "UPDATE
      gp_orders
    SET
      invoice_doc_id = ".($invoice_doc_id ? "'$invoice_doc_id'" : 'NULL')." -- Unique Index forces us to use NULL rather than ''
    WHERE
      invoice_number = {$order[0]['invoice_number']}";

    $mysql->run($sql);

    $elapsed = ceil(microtime(true) - $start);
    $gd_merge_timers['export_gd_update_invoice'] += $elapsed;

    if ($elapsed > 20) {
        SirumLog::notice(
            'export_gd_update_invoice: Took to long to process',
            [
                "invoice_number" => $order[0]['invoice_number']
            ]
        );
    }

    return $order;
}

function export_gd_print_invoice($order)
{
    global $gd_merge_timers;
    $start = microtime(true);
    SirumLog::notice(
        "export_gd_print_invoice: start",
        [
            "invoice_number" => $order[0]['invoice_number'],
            "invoice_doc_id" => $order[0]['invoice_doc_id']
        ]
    );

    $args = [
        'method'     => 'moveFile',
        'fileId'     => $order[0]['invoice_doc_id'],
        'file'       => 'Invoice #'.$order[0]['invoice_number'],
        'fromFolder' => INVOICE_PENDING_FOLDER_NAME,
        'toFolder'   => INVOICE_PUBLISHED_FOLDER_NAME,
    ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    $time = ceil(microtime(true) - $start);

    SirumLog::debug(
        'export_gd_print_invoice',
        [
            "invoice_number" => $order[0]['invoice_number'],
            "invoice_doc_id" => $order[0]['invoice_doc_id'],
            "result"         => $result,
            "time"           => $time
        ]
    );

    $gd_merge_timers['export_gd_print_invoice'] += ceil(microtime(true) - $start);
}

//Cannot delete (with this account) once published
function export_gd_publish_invoice($order, $mysql, $retry = false)
{
    global $gd_merge_timers;
    $start = microtime(true);

    // Check to see if the file we have exists
    if (!empty($order[0]['invoice_doc_id'])) {
        $meta = gdoc_details($order[0]['invoice_doc_id']);
    }

    if (!isset($meta) || $meta->parent->name != INVOICE_PENDING_FOLDER_NAME || $meta->trashed) {
        // The current invoice is trash.  Make a new invoice
        $update_reason = "export_gd_publish_invoice: invoice didn't exist so trying to (re)make it";

        SirumLog::warning(
            $update_reason,
            [
                'invoice_number' => $order[0]['invoice_number'],
                'invoice_doc_id' => $order[0]['invoice_doc_id'],
                'meta'           => $meta
            ]
        );

        $order = export_gd_update_invoice($order, $update_reason, $mysql);
    }

    $args = [
        'method'   => 'publishFile',
        'fileId'   => $order[0]['invoice_number'],
        'file'     => 'Invoice #' . $order[0]['invoice_number'],
        'folder'   => INVOICE_PENDING_FOLDER_NAME,
    ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    $time = ceil(microtime(true) - $start);

    SirumLog::debug(
        'export_gd_publish_invoice success',
        [
            "invoice_number" => $order[0]['invoice_number'],
            "result"         => $result,
            "time"           => $time
        ]
    );

    $gd_merge_timers['export_gd_publish_invoice'] += ceil(microtime(true) - $start);

    return $order;
}

/**
 * Delete a specific Invoice by ID
 *
 * @param string $invoice_id    This should be the invoice_doc_id, but if the data
 *      is an int < 10,000,000 we assum it is and invoice_number so we fetch the
 *      invoice_doc_id
 * @param boolean $async        (Optional) Should the request be sent to a queue
 *      or should we wait while it runs
 *
 * @return boolean
 */
function export_gd_delete_invoice($invoice_id, $async = true)
{
    $invoice_doc_id = $invoice_id;

    // If it is a number less than 10,000,000 we can assume it's an
    // invoice_number and not a doc_id.  We want the doc ID so we should get it
    if (is_numeric($invoice_id) && strlen($invoice_id) < 10000000) {
        // Go get the doc ID using a simple model
        $order = new Order(['invoice_number' => $invoice_id]);
        
        if ($order->loaded
            && isset($order->invoice_doc_id)) {
                $invoice_doc_id = $order->invoice_doc_id;
        } else {
            return false;
        }
    }

    $delete_request            = new Delete();
    $delete_request->method    = 'v2/removeFile';
    $delete_request->fileId    = $invoice_doc_id;
    $delete_request->group_id = $invoice_doc_id;

    if ($async) {
        $gdq = new GoogleDocsQueue();
        $gdq->send($delete_request);
    } else {
        $start  = microtime(true);
        $args   = $delete_request->toArray();
        //$result = gdoc_post(GD_HELPER_URL, $args);
        $time   = ceil(microtime(true) - $start);

        SirumLog::debug(
            'Invoice deleted while application waited',
            [
                "invoice_id" => $invoice_id,
                "result"     => $result,
                "time"       => $time
            ]
        );

        global $gd_merge_timers;
        $gd_merge_timers['export_gd_delete_invoice'] += ceil($time);
    }

    return true;
}
