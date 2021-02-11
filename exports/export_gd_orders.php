<?php

require_once 'helpers/helper_appsscripts.php';

use GoodPill\AWS\SQS\GoogleAppRequest\Invoice\{
    Complete,
    Delete,
    Move,
    Publish
};

use GoodPill\AWS\SQS\GoogleAppQueue;
use GoodPill\DataModels\GoodPillOrder;
use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

global $gd_merge_timers;

$gd_merge_timers = [
    'export_gd_update_invoice'  => 0,
    'export_gd_delete_invoice'  => 0,
    'export_gd_print_invoice'   => 0,
    'export_gd_publish_invoice' => 0
];


/**
 * Create in invoice from a template.  Also update the database with the new
 * invoice_doc_id.  After the doc ID is complete a complete/merge request will
 * be processed or queued
 *
 * @param  int     $invoice_number The invoice number for teh gp_orders table
 * @param  boolean $async          Should we wait on the script to finish or
 *      send the request to a queue
 * @return null|string             null if the document wasn't generate or the
 *      invoice_doc_id if it was successvful
 */
function export_gd_create_invoice(int $invoice_number, bool $async = true) : ?string
{
    // If there is a current invoice, delete it before moving on
    $order = new GoodPillOrder(['invoice_number' => $invoice_number]);

    // No order so nothing to do
    if (!$order->loaded) {
        return null;
    }

    if (isset($order->invoice_number)) {
        export_gd_delete_invoice($invoice_number);
    }

    $args = [
        'method'   => 'v2/createInvoice',
        'templateId' => INVOICE_TEMPLATE_ID,
        'fileName' => "Invoice #{$invoice_number}",
        'folderId' => GD_FOLDER_IDS[INVOICE_PENDING_FOLDER_NAME],
    ];

    $result = json_decode(gdoc_post(GD_MERGE_URL, $args));

    var_dump($results);
    exit;

    if (isset($result->invoice_doc_id)) {
        $invoice_doc_id = $result->invoice_doc_id;
        $gpdb           = Goodpill::getConnection();
        $pdo            = $gpdb->prepare(
            "UPDATE gp_orders
                SET invoice_doc_id = :invoice_doc_id
                WHERE invoice_number = :invoice_number"
        );

        $pdo->bindParam(':invoice_doc_id', $invoice_doc_id, \PDO::PARAM_STR);
        $pdo->bindParam(':invoice_number', $invoice_number, \PDO::PARAM_INT);
        //$pdo->execute();

        $results = export_gd_complete_invoice($invoice_number, $async);

        // If we weren't async, we should return null if the gd_complete step
        // failed to proccess
        if (!$async && !$results) {
            return null;
        }

        return $invoice_doc_id;
    }

    // We failed to get an id, so we should handle that
    return null;
}

/**
 * Send the order data to google Docs to render the invoice.
 *
 * @param  int     $invoice_number The invoice number fromt eh gp_orders table
 * @param  boolean $async          Should we wait for it to complete or send it
 *      to a queue
 * @return bool                    True if the item was queued or completed
 */
function export_gd_complete_invoice(int $invoice_number, bool $async = true) : bool
{
    // Get full orders
    $order = new GoodPillOrder(['invoice_number' => $invoice_number]);
    $legacy_order = $order->getLegacyOrder();

    $args = [
        'method'       => 'completeInvoice',
        'fileId'       => $order->invoice_doc_id,
        'orderDetails' => $legacy_order
    ];

    $complete_request                = new Complete();
    $complete_request->fileId        = $order->invoice_doc_id;
    $complete_request->group_id      = "invoice-{$order->invoice_number}";
    $complete_request->orderDetails  = $legacy_order;

    if ($async) {
        $gdq = new GoogleAppQueue();
        $gdq->send($complete_request);
        return true;
    }

    $result = gdoc_post(GD_MERGE_URL, $args);

    if ($results->results == 'success') {
        return true;
    }

    GPLog::error(
        "Failed to complete invoice {$invoice_number}",
        [ 'results' => $result ]
    );

    return false;
}

/**
 * Publish an order.  This will make the order not editable.  We need to pass back
 * the full order because we could modify the order by doing an update request
 *
 * @param  array   $order  An array of all the order items
 * @param  boolean $async  Should we wait on the publish request or should it
 *      be a queued request
 * @return array  The order including any changes
 */
function export_gd_publish_invoice(array $order, bool $async = true) : array
{
    $invoice_doc_id = $order[0]['invoice_doc_id'];
    $invoice_number = $order[0]['invoice_number'];

    // Check to see if the file we have exists, and it is in the correct place
    // and not trashed
    if (!empty($invoice_doc_id)) {
        $meta = gdoc_details($order[0]['invoice_doc_id']);
    }

    if (
        !isset($meta)
        || (
                $meta->parent->name != INVOICE_PENDING_FOLDER_NAME
                || $meta->trashed
           )
    ) {
        $invoice_doc_id = export_gd_create_invoice($order[0]['invoice_number'], $async);

        if (!$invoice_doc_id) {
            // We failed to create the invoice, so we need to log an error and leave
            GPLog::error(
                "Faile to create missing invoice",
                [
                    'invoice_number'     => $invoice_number,
                    'order'              => $order,
                    'meta'               => $meta
                ]
            );
            return $order;
        }

        for ($i = 0; $i < count($order); $i++) {
            $order[$i]['invoice_doc_id'] = $invoice_doc_id;
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

    var_dump($publish_request->toArray());

    $results = gdoc_post($url, $publish_request->toArray());

    GPLog::debug(
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

    GPLog::debug(
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

    GPLog::debug(
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
