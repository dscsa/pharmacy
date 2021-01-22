<?php

require_once 'helpers/helper_appsscripts.php';

use Sirum\AWS\SQS\GoogleAppRequest\Invoice\{
    Delete,
    Move,
    Publish
};

use Sirum\AWS\SQS\GoogleAppQueue;
use Sirum\DataModels\GoodPillOrder;
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



/**
 * Publish an order.  This will make the order not editable.  We need to pass back
 * the full order because we could modify the order by doing an update request
 *
 * @param  array   $order
 * @param  boolean $async [description]
 * @return [type]         [description]
 */
function export_gd_publish_invoice($order, $async = true)
{
    $invoice_doc_id = $order[0]['invoice_doc_id'];
    $invoice_number = $order[0]['invoice_number'];

    // Check to see if the file we have exists, and it is in the correct place
    // and not trashed
    if (!empty($invoice_doc_id)) {
        $meta = gdoc_details($order[0]['invoice_doc_id']);
        if ($meta->parent->name != INVOICE_PENDING_FOLDER_NAME || $meta->trashed) {
            // The current invoice is trash.  Make a new invoice
            $update_reason = "export_gd_publish_invoice: invoice didn't exist so trying to (re)make it";
            $mysql = new Mysql_Wc()
            $order = export_gd_update_invoice($order, $update_reason, $mysql);

            SirumLog::warning(
                $update_reason,
                [
                    'invoice_number'     => $invoice_number,
                    'old_invoice_doc_id' => $invoice_doc_id,
                    'new_invoice_doc_id' =>  $order[0]['invoice_doc_id'],
                    'meta'               => $meta
                ]
            );

            $invoice_doc_id = $order[0]['invoice_doc_id'];
        }
    }

    $publish_request             = new Publish();
    $publish_request->fileId     = $invoice_doc_id;
    $publish_request->group_id   = "invoice-{$invoice_number}";

    if ($async) {
        $gdq = new GoogleAppQueue();
        $gdq->send($publish_request);
        return $order;
    }

    $results = gdoc_post($url, $publish_request->toArray());

    SirumLog::debug(
        'export_gd_publish_invoice published while application waited',
        [
            "invoice_number" => $order[0]['invoice_number'],
            "result"         => $result,
            "time"           => $time
        ]
    );

    return $order;
}

/**
 * Print the invoice by moving it to a printing folder
 * @param  int/string  $invoice_number Should be the invoice number, but if its
 *      a string, we assume it's a invoice_doc_id
 * @param  boolean $async              (Optional) should we wait on the request
 *      or queue the request
 * @return object                      The results of the request
 */
function export_gd_print_invoice($invoice_number, $async = true)
{

    if (!$invoice_doc_id = findInvoiceDocId($invoice_number)) {
        return false;
    };

    $print_request             = new Move();
    $print_request->fileId     = $invoice_doc_id;
    $print_request->folderId   = GD_FOLDER_IDS[INVOICE_PUBLISHED_FOLDER_NAME];
    $print_request->group_id   = "invoice-{$invoice_number}";

    if ($async) {
        $gdq = new GoogleAppQueue();
        return $gdq->send($print_request);
    }

    $results = gdoc_post($url, $print_request->toArray());

    SirumLog::debug(
        'export_gd_print_invoice while application waited',
        [
            "invoice_number" => $invoice_number,
            "invoice_doc_id" => $invoice_doc_id,
            "result"         => $result,
            "time"           => $time
        ]
    );
}

/**
 * Delete a specific Invoice by ID
 *
 * @param string $invoice_number This should be the invoice_number, but could be a doc_id
 * @param boolean $async         (Optional) Should the request be sent to a queue
 *      or should we wait while it runs
 *
 * @return boolean
 */
function export_gd_delete_invoice($invoice_number, $async = true)
{
    if (!$invoice_doc_id = findInvoiceDocId($invoice_number)) {
        return false;
    };

    $delete_request            = new Delete();
    $delete_request->fileId    = $invoice_doc_id;
    $delete_request->group_id  = "invoice-{$invoice_number}";

    if ($async) {
        $gdq = new GoogleAppQueue();
        return $gdq->send($delete_request);
    }

    // Since we aren't async, lets do the work right nwo
    $results = gdoc_post($url, $delete_request->toArray());

    SirumLog::debug(
        'Invoice printed while application waited',
        [
            "invoice_id" => $invoice_id,
            "result"     => $result,
            "time"       => $time
        ]
    );

    return $results;
}

/**
 * Just to make sure, we check to see if the id looks like a legit gd id.
 * If it isn't, we assume its and invoice ID and try to look it up in the db
 * @param  string $possible_doc_id Should most often be a google doc ID
 *      but could be an invoice id
 * @return string|false  Should be a GD doc id if we can find it
 */
function findInvoiceDocId($likely_invoice_number)
{
    // If it is a number less than 10,000,000 we can assume it's an
    // invoice_number and not a doc_id.  We want the doc ID so we should get it
    if (is_numeric($likely_invoice_number) && strlen($likely_invoice_number) < 10000000) {
        // Go get the doc ID using a simple model
        $order = new GoodPillOrder(['invoice_number' => $likely_invoice_number]);

        if ($order->loaded
            && isset($order->invoice_doc_id)) {
                return $order->invoice_doc_id;
        } else {
            return false;
        }
    }

    return $likely_invoice_number;
}
