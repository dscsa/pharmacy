<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use GoodPill\Models\GpPatient;
use GoodPill\Models\GpOrderItem;
use GoodPill\Models\Carepoint\CpCsomShipUpdate;
use GoodPill\Models\Carepoint\CpCsomShip;
use GoodPill\Events\Order\Shipped as ShippedEvent;
use GoodPill\Events\Order\Delivered as DeliveredEvent;
use GoodPill\AWS\SQS\GoogleAppRequest\Invoice\Move;
use GoodPill\AWS\SQS\GoogleAppRequest\Invoice\Complete;
use GoodPill\AWS\SQS\GoogleAppRequest\Invoice\Publish;
use GoodPill\AWS\SQS\GoogleAppRequest\Invoice\Delete;
use GoodPill\AWS\SQS\GoogleAppQueue;
use GoodPill\Logging\GPLog;

require_once "helpers/helper_full_order.php";
require_once "helpers/helper_appsscripts.php";

/**
 * Class GpOrder
 */
class GpOrder extends Model
{
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_orders';

    /**
     * The primary_key for this item
     * @var string
     */
    protected $primaryKey = 'invoice_number';

    /**
     * Does the database contain an incrementing field?
     * @var bool
     */
    public $incrementing = false;

    /**
     * Does the database contain timestamp fields
     * @var bool
     */
    public $timestamps = false;

    /**
     * Fields that should be cast when they are set
     * @var array
     */
    protected $casts = [
        'invoice_number'        => 'int',
        'patient_id_cp'         => 'int',
        'patient_id_wc'         => 'int',
        'count_items'           => 'int',
        'count_filled'          => 'int',
        'count_nofill'          => 'int',
        'payment_total_default' => 'int',
        'payment_total_actual'  => 'int',
        'payment_fee_default'   => 'int',
        'payment_fee_actual'    => 'int',
        'payment_due_default'   => 'int',
        'payment_due_actual'    => 'int'
    ];

    /**
     * Fields that hold dates
     * @var array
     */
    protected $dates = [
        'order_date_added',
        'order_date_changed',
        'order_date_updated',
        'order_date_dispensed',
        'order_date_shipped',
        'order_date_delivered',
        'order_date_returned'
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'patient_id_cp',
        'patient_id_wc',
        'count_items',
        'count_filled',
        'count_nofill',
        'order_source',
        'order_stage_cp',
        'order_stage_wc',
        'order_status',
        'invoice_doc_id',
        'order_address1',
        'order_address2',
        'order_city',
        'order_state',
        'order_zip',
        'tracking_number',
        'order_date_added',
        'order_date_changed',
        'order_date_updated',
        'order_date_dispensed',
        'order_date_shipped',
        'order_date_delivered',
        'order_date_returned',
        'payment_total_default',
        'payment_total_actual',
        'payment_fee_default',
        'payment_fee_actual',
        'payment_due_default',
        'payment_due_actual',
        'payment_date_autopay',
        'payment_method_actual',
        'coupon_lines',
        'order_note'
    ];

    /*
     *
     * Relationships
     *
     */

    /**
     * Link to the GpPatient object on the patient_id_cp
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(GpPatient::class, 'patient_id_cp', 'patient_id_cp');
    }

    /**
     * Link the the GpOrderItems object on the invoice_number and sort newest to oldest
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(GpOrderItem::class, 'invoice_number', 'invoice_number')
                    ->orderBy('invoice_number', 'desc');
    }

    /**
     * Get the shipment for this item
     * @return CpCsomShipUpdate
     */
    public function getShipUpdate()
    {
        return CpCsomShipUpdate::where('order_id', $this->order_id)->firstOrNew();
    }

    /**
     * Get the shipment for this item
     * @return CpCsomShipUpdate
     */
    public function getShipment()
    {
        return CpCsomShip::where('order_id', $this->order_id)->first();
    }

    /**
     * Link to the GpPatient object on the patient_id_cp
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function pend_group()
    {
        return $this->hasOne(GpPendGroup::class, 'invoice_number', 'invoice_number');
    }


    /*
     * Condition Methods:  These methods are all meant to be conditional and should
     *  all return booleans.  The methods should be named with appropriate descriptive verbs
     *  ie: isShipped()
     *      hasItems()
     */

    /**
     * Has the order been marked as shipped
     *  An order will be considered shipped if it
     *     Exist in the Database
     *     AND Has a Shipped Date in the database
     *     AND (
     *         The Shipped Date is more than 12 hours Ago
     *         OR (
     *             There is a tracking number
     *             AND the Shipped Date is more than 10 minutes ago
     *         )
     *      )
     *
     * @return bool
     */
    public function isShipped() : bool
    {
        // We add a 12 hour padding to the order_date_shipped incase they
        // make changes before it leaves the office
        return (
             $this->exists
             && !empty($this->order_date_shipped)
             && (
                 strtotime($this->order_date_shipped) + (60 * 60 * 12) > time()
                 || (
                     isset($this->tracking_number)
                     // Add a 10 minute window just so things that happen on
                     // the same sync execution don't throw an error
                     && strtotime($this->order_date_shipped) + (60 * 10) > time()
                 )
             )
         );
    }

    /**
     * Has the order been marked delivered
     *      An order will be considered delivered if it order_date_delivered is not empty
     * @return bool
     */
    public function isDelivered() : bool
    {
        // We add a 12 hour padding to the order_date_shipped incase they
        // make changes before it leaves the office
        return (
             $this->exists
             && !empty($this->order_date_delivered)
         );
    }

    /**
     * Has the order been dispensed
     * An order will be considered dispensed if it
     *     Exists in the Database
     *     AND There is a dispensed date for the order
     * @return bool
     */
    public function isDispensed() : bool
    {
        return ($this->exists && !empty($this->order_date_dispensed));
    }


    /*
     * Other Methods
     */

    /**
     * Override the save function so it sends data into Carepoint automatically
     *
     * @param  array $options Parent signature match.
     * @return void
     */
    public function save(array $options = [])
    {
        // Save the data first, so that getChanges will actually show what was changed.
        $changes = $this->getDirty();
        $results = parent::save($options);

        // If there is an update the the shipping address,
        // We Need to strore it in Carepoint
        if (!empty(
            array_intersect(
                [
                    'order_address1',
                    'order_address2',
                    'order_city',
                    'order_state',
                    'order_zip'
                ],
                array_keys($changes)
            )
        )) {
            $orderShipData = $this->getShipment();
            if (!is_null($orderShipData)) {
                $orderShipData->ship_addr1 = $this->order_address1;
                $orderShipData->ship_addr2 = $this->order_address2;
                $orderShipData->ship_city = $this->order_city;
                $orderShipData->ship_state_cd = $this->order_state;
                $orderShipData->ship_zip = $this->order_zip;
                $orderShipData->save();
            }
        }

        return $results;
    }

    /**
     * Delete the previous shipping data
     * @return boolean Was the shipment deleted.
     */
    public function deleteShipment() : bool
    {
        if (!$this->isShipped()) {
            return false;
        }
        // Model should cast to date
        $shipUpdate = $this->getShipUpdate();

        GPLog::debug(
            sprintf(
                "The shipping label for %s with tracking number %s has been DELETED",
                $this->invoice_number,
                $shipUpdate->TrackingNumber
            ),
            [ "invoice_number" => $this->invoice_number ]
        );

        $shipUpdate->delete();

        $shipment = $this->getShipment();
        if (!is_null($shipment)) {
            $shipment->delete();
        }

        $this->order_date_shipped = null;
        $this->tracking_number    = null;
        $this->save();

        GPLog::debug(
            sprintf(
                "The shipping label for %s with tracking number %s has been DELETED",
                $this->invoice_number,
                $shipUpdate->TrackingNumber
            ),
            [ "invoice_number" => $this->invoice_number ]
        );

        return true;
    }

    /**
     * Update the order as shipped
     * @param  string $ship_date       A stringtotime compatible utc date.
     * @param  string $tracking_number The tracking number for the shipment.
     * @return bool                    Was the shipment updated.
     */
    public function markShipped(string $ship_date, string $tracking_number) : bool
    {
        if ($this->isShipped()) {
            return false;
        }

        $ship_date = strtotime($ship_date);

        // Model should cast to date
        $ship_update                 = $this->getShipUpdate();

        if (!$ship_update->exists) {
            $ship_update->order_id = $this->order_id;
        }

        $ship_update->ship_date      = $ship_date;
        $ship_update->TrackingNumber = $tracking_number;
        $ship_update->save();

        GPLog::debug(
            sprintf(
                "A shipping label for %s with tracking number %s has been created",
                $this->invoice_number,
                $ship_update->TrackingNumber
            ),
            [ "invoice_number" => $this->invoice_number ]
        );

        $this->order_date_shipped = $ship_date;
        $this->tracking_number    = $tracking_number;
        $this->save();

        $shipped = new ShippedEvent($this);
        $shipped->publish();

        return true;
    }

    /**
     * Update the order as delivered
     * @param  string $delivered_date   A stringtotime compatible utc date.
     * @param  string $tracking_number The tracking number for the shipment.
     * @return bool                  Was the shipment updated
     */
    public function markDelivered(string $delivered_date, string $tracking_number) : bool
    {
        if ($this->isDelivered()) {
            //return false;
        }

        $ship_update = $this->getShipUpdate();

        $delivered_date = strtotime($delivered_date);

        if (!$ship_update->exists) {
            $ship_update->ship_date = date(strtotime('-3 day', $delivered_date));
            $ship_update->TrackingNumber = $tracking_number;
        }

        // Model should cast to date
        $ship_update->DeliveredDate = $delivered_date;
        $ship_update->save();

        $this->order_date_delivered = $delivered_date;
        $this->save();

        GPLog::debug(
            sprintf(
                "The shipment for %s with tracking number %s has been delivered",
                $this->invoice_number,
                $ship_update->TrackingNumber
            ),
            [ "invoice_number" => $this->invoice_number ]
        );

        $shipped = new DeliveredEvent($this);
        $shipped->publish();

        return true;
    }

    /**
     * Update the order as delivered
     * @param  string $status_date     A stringtotime compatible utc date.
     * @param  string $tracking_number The tracking number for the shipment.
     * @return bool                    Was the shipment updated.
     */
    public function markReturned(string $status_date, string $tracking_number) : bool
    {
        if ($this->isDelivered()) {
            return false;
        }

        $ship_update = $this->getShipUpdate();

        $status_date = strtotime($status_date);

        if (!$ship_update->exists) {
            $ship_update->ship_date = date(strtotime('-3 day', $status_date));
            $ship_update->TrackingNumber = $tracking_number;
            $ship_update->save();
        }

        $this->order_date_returned = $status_date;

        $this->save();

        GPLog::debug(
            sprintf(
                "The shipment for %s with tracking number %s has been RETURNED",
                $this->invoice_number,
                $ship_update->TrackingNumber
            ),
            [ "invoice_number" => $this->invoice_number ]
        );

        $shipped = new ReturnedEvent($this);
        $shipped->publish();

        return true;
    }

    /**
     * Getters : Retrieve and format data outside of the raw db info
     */

    /**
     * The order number is not the same as the invoice_number.  Lets subtract 2
     * @return integer
     */
    public function getOrderId()
    {
        return $this->invoice_number - 2;
    }

    public function getOrderIdAttribute() {
        return $this->invoice_number - 2;
    }

    /**
     * Get the tracking url for the order
     * @param  bool $short Optional Should we use the url shortener.
     * @return string
     */
    public function getTrackingUrl(bool $short = false) : ?string
    {
        if (strlen($this->tracking_number) == 22) {
            $tracking_url = 'https://tools.usps.com/go/TrackConfirmAction?tLabels=';
        } elseif (
            strlen($this->tracking_number) == 15
            || strlen($this->tracking_number) == 12
        ) { //Ground or Express
            $tracking_url = 'https://www.fedex.com/apps/fedextrack/?tracknumbers=';
        } else {
            $tracking_url = "#";
        }

        $tracking_url .= $this->tracking_number;

        if ($short && strlen($tracking_url) > 20) {
            $links = short_links([ 'tracking_url'  => $tracking_url ]);
            $tracking_url = $links['tracking_url'];
        }

        return $tracking_url;
    }

    /**
     * Get a url to view the invoice
     * @param  bool $short Optional Should we use a shortener service.
     * @return string
     */
    public function getInvoiceUrl(bool $short = false) : string
    {
        $invoice_url = "https://docs.google.com/document/d/{$this->invoice_doc_id}/pub?embedded=true";

        if ($short) {
            $links = short_links([ 'invoice_url'  => $invoice_url ]);
            $invoice_url = $links['invoice_url'];
        }

        return $invoice_url;
    }

    /**
     * User the relationship to get filled and unfilled items for the order
     * @param bool $filled Optional If a bool, return filled or not filled items.
     * @return mixed
     */
    public function getFilledItems(bool $filled = true)
    {
        if ($filled) {
            return $this->items()->whereNotNull('rx_dispensed_id');
        } else {
            return $this->items()->whereNull('rx_dispensed_id');
        }
    }

    public function getNoRefilledItems()
    {
        return $this->items()->whereNull('refills_dispensed');
    }

    /**
     * Get to old order array
     * @return null|array
     */
    public function getLegacyOrder() : ?array
    {
        if ($this->exists) {
            return load_full_order(
                ['invoice_number' => $this->invoice_number ],
                (new \Mysql_Wc())
            );
        }

        return null;
    }

    /**
     * Create an invoice request for printing.  Moves the invoice into the print folder so the
     * autoprint can finish printing the file
     * @return boolean true if the request was queued.
     */
    public function printInvoice() : bool
    {
        if (empty($this->invoice_doc_id)) {
            $this->createInvoice();
            $this->publishInvoice();
        }

        $print_request             = new Move();
        $print_request->fileId     = $this->invoice_doc_id;
        $print_request->folderId   = GD_FOLDER_IDS[INVOICE_PUBLISHED_FOLDER_NAME];
        $print_request->group_id   = "invoice-{$this->invoice_number}";

        $gdq = new GoogleAppQueue();
        return (bool) $gdq->send($print_request);
    }

    /**
     * Create an invoice by sending the invoice detail to the appscript
     * @return boolean True if the invoice was created.
     */
    public function createInvoice() : bool
    {
        // No order so nothing to do
        if (!$this->exists) {
            return null;
        }

        //
        if (isset($this->invoice_doc_id)) {
            $this->deleteInvoice();
        }

        $args = [
            'method'   => 'v2/createInvoice',
            'templateId' => INVOICE_TEMPLATE_ID,
            'fileName' => "Invoice #{$this->invoice_number}",
            'folderId' => GD_FOLDER_IDS[INVOICE_PENDING_FOLDER_NAME],
        ];

        $response = json_decode(gdoc_post(GD_MERGE_URL, $args));
        $results  = $response->results;

        if ($response->results == 'success') {
            $invoice_doc_id = $response->doc_id;
            $this->invoice_doc_id = $response->doc_id;
            $this->save();

            // Queue up the task to complete the invoice
            $legacy_order = $this->getLegacyOrder();
            $complete_request                = new Complete();
            $complete_request->fileId        = $this->invoice_doc_id;
            $complete_request->group_id      = "invoice-{$this->invoice_number}";
            $complete_request->orderData  = $legacy_order;

            $gdq = new GoogleAppQueue();
            $gdq->send($complete_request);
            return true;
        }

        // We failed to get an id, so we should handle that
        return false;
    }

    /**
     * Queue up a request to delete an invoice
     * @return boolean Was the request queued
     */
    public function deleteInvoice() : bool
    {
        if (empty($this->invoice_doc_id)) {
            return false;
        }

        $delete_request            = new Delete();
        $delete_request->fileId    = $this->invoice_doc_id;
        $delete_request->group_id  = "invoice-{$this->invoice_number}";

        $this->invoice_doc_id = null;
        $this->save();

        $gdq = new GoogleAppQueue();
        return (bool) $gdq->send($delete_request);
    }

    /**
     * Publish an invoice so it can be viewed publically
     * @return boolean Did the request get sent
     */
    public function publishInvoice() : bool
    {
        if (!$this->invoiceHasPrinted()) {
            $this->createInvoice();
        }

        $publish_request             = new Publish();
        $publish_request->fileId     = $this->invoice_doc_id;
        $publish_request->group_id   = "invoice-{$this->invoice_number}";

        $gdq = new GoogleAppQueue();
        return (bool) $gdq->send($publish_request);
    }

    /**
     * Check to see if the invoice has actually printed
     * @return boolean True if the invoice exists, is not trashed, and is not in the pending folder.
     */
    public function invoiceHasPrinted() : bool
    {
        if (!empty($this->invoice_doc_id)) {
            $meta = gdoc_details($this->invoice_doc_id);
        }

        return (
            isset($meta)
            && !$meta->trashed
            && $meta->parent->name != INVOICE_PENDING_FOLDER_NAME
        );
    }

    /**
     * Cancel the comm calendar events
     * @param  array  $events The type of events to Cancel
     * @return void
     */
    public function cancelEvents(?array $events = []) : void
    {
        cancel_events_by_order(
            $this->invoice_number,
            'log should be above',
            $events
        );
    }
}
