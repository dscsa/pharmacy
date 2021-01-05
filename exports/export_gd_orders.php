<?php

require_once 'helpers/helper_appsscripts.php';

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

    if (@$order[0]['invoice_doc_id']) {
        export_gd_delete_invoice($order[0]['invoice_doc_id']);
    }

    $args = [
        'method'   => 'mergeDoc',
        'template' => 'Invoice Template v1',
        'file'     => 'Invoice #'.$order[0]['invoice_number'],
        'folder'   => INVOICE_PENDING_FOLDER_NAME,
        'order'    => $order
    ];

    echo "\n creating invoice ".$order[0]['invoice_number']." (".$order[0]['order_stage_cp'].")";

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
 * Set an invoice as published so it can't be modified any longer
 * @param  array    $order The order to be published
 * @param  Mysql_Wc $mysql The Mysql connection
 * @return void
 */
function export_gd_publish_invoice($order, $mysql)
{

    // Check to see if current invoice is avaliable.
    // If it isn't request and update of the invoice.
    // Request to publish the invoice.
    if ($order[0]['invoice_doc_id']) {
        $meta = gdoc_details($order[0]['invoice_doc_id']);
    }

    // Check to see if the file we have exists
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

    // Publish the file by id not search
    $args = [
        'method'   => 'publishFile',
        'fileId'   => $order[0]['invoice_number']
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
 * Move an invoice to the published folder so it will print
 * @param  array $order An order that needs to be printed
 * @return void
 */
function export_gd_print_invoice($order)
{
    // Rework this to put the item in the queue
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
        'fromFolder' => INVOICE_PENDING_FOLDER_NAME,
        'toFolder'   => INVOICE_PUBLISHED_FOLDER_NAME,
    ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    SirumLog::debug(
        'export_gd_print_invoice',
        [
            "invoice_number" => $order[0]['invoice_number'],
            "invoice_doc_id" => $order[0]['invoice_doc_id'],
            "result"         => $result,
        ]
    );
}

/**
 * Delete a document from Google drive.
 * @param  string  $identifier The identifier to use when removing files
 * @param  boolean $is_doc_id  Is the identifier a specific file or is in a
 *      string that we should use to search for files to delete
 * @return void
 */
function export_gd_delete_invoice($identifier, $async = true, $is_doc_id = true)
{

    $args = [
        'method'   => 'removeFiles',
        'folder'   => INVOICE_PENDING_FOLDER_NAME
    ];

    if ($invoice_doc_id) {
        $args['fileId'] = $identifier;
    } else {
        $args['file'] = 'Invoice #' . $identifier;
    }

    if ($async) {
        // Add the command to the args
        // Send it to the appropriate Google Queue
        SirumLog::debug(
            'export_gd_delete_invoice queued for later',
            [
                "identifier"     => $identifier,
                "is_doc_id"      => $is_doc_id,
                "result"         => $result,
                "time"           => $time
            ]
        );
    } else {
        $result = gdoc_post(GD_HELPER_URL, $args);

        SirumLog::debug(
            'export_gd_delete_invoice',
            [
                "identifier"     => $identifier,
                "is_doc_id"      => $is_doc_id,
                "result"         => $result,
                "time"           => $time
            ]
        );
    }



}
