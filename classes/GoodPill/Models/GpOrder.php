<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use GoodPill\Models\GpPatient;
use GoodPill\Models\GpOrderItem;
use GoodPill\Models\Carepoint\CpOrderShipment;
use GoodPill\Events\Order\Shipped as ShippedEvent;
use GoodPill\Events\Order\Delivered as DeliveredEvent;

require_once "helpers/helper_full_order.php";

/**
 * Class GpOrder
 *
 * @property int $invoice_number
 * @property int|null $patient_id_cp
 * @property int|null $patient_id_wc
 * @property int $count_items
 * @property int|null $count_filled
 * @property int|null $count_nofill
 * @property string|null $order_source
 * @property string|null $order_stage_cp
 * @property string|null $order_stage_wc
 * @property string|null $order_status
 * @property string|null $invoice_doc_id
 * @property string|null $order_address1
 * @property string|null $order_address2
 * @property string|null $order_city
 * @property string|null $order_state
 * @property string|null $order_zip
 * @property string|null $tracking_number
 * @property Carbon|null $order_date_added
 * @property Carbon|null $order_date_changed
 * @property Carbon $order_date_updated
 * @property Carbon|null $order_date_dispensed
 * @property Carbon|null $order_date_shipped
 * @property Carbon|null $order_date_returned
 * @property int|null $payment_total_default
 * @property int|null $payment_total_actual
 * @property int|null $payment_fee_default
 * @property int|null $payment_fee_actual
 * @property int|null $payment_due_default
 * @property int|null $payment_due_actual
 * @property string|null $payment_date_autopay
 * @property string|null $payment_method_actual
 * @property string|null $coupon_lines
 * @property string|null $order_note
 *
 * @package App\Models
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
     * Does the database contining an incrementing field?
     * @var boolean
     */
    public $incrementing = false;

    /**
     * Does the database contining timestamp fields
     * @var boolean
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
     * @return Collection
     */
    public function patient()
    {
        return $this->belongsTo(GpPatient::class, 'patient_id_cp', 'patient_id_cp');
    }

    /**
     * Link the the GpOrderItems object on the invoice_number and sort newest to oldest
     * @return Collection
     */
    public function items()
    {
        return $this->hasMany(GpOrderItem::class, 'invoice_number', 'invoice_number')
                    ->orderBy('invoice_number', 'desc');
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
     * Update the order as shipped
     * @param  string $ship_date       A stringtotime compatible utc date
     * @param  string $tracking_number The tracking number for the shipment
     * @return bool                    Was the shipment updatedated
     */
    public function markShipped($ship_date, $tracking_number) : bool
    {
        if ($this->isShipped()) {
            return false;
        }

        // Model should cast to date
        $shipment                 = CpOrderShipment::firstOrCreate(['order_id' => $this->getOrderId()]);
        $shipment->ship_date      = $ship_date;
        $shipment->TrackingNumber = $tracking_number;
        $shipment->save();

        $this->order_date_shipped = $ship_date;
        $this->tracking_number    = $tracking_number;
        $this->save();

        $shipped = new ShippedEvent($this);
        $shipped->publish();

        return true;
    }

    /**
     * Update the order as delivered
     * @param  string delivered_date   A stringtotime compatible utc date
     * @param  string $tracking_number The tracking number for the shipment
     * @return bool                    Was the shipment updated
     */
    public function markDelivered($delivery_date, $tracking_number) : bool
    {
        if ($this->isDelivered()) {
            return false;
        }

        $shipment = CpOrderShipment::where('order_id', $this->getOrderId())->firstOrNew();

        if (!$shipment->exists) {
            $shipment->ship_date = date(strtotime('-3 day', strtotime($delivery_date)));
            $shipment->TrackingNumber = $tracking_number;
        }

        // Model should cast to date
        $shipment->DeliveredDate = $delivery_date;
        $shipment->save();

        $this->order_date_delivered = $delivery_date;
        $this->save();

        $shipped = new DeliveredEvent($this);
        $shipped->publish();

        return true;
    }

    /**
     * Update the order as delivered
     * @param  string delivered_date   A stringtotime compatible utc date
     * @param  string $tracking_number The tracking number for the shipment
     * @return bool                    Was the shipment updated
     */
    public function markReturned($status_date, $tracking_number) : bool
    {
        if ($this->isDelivered()) {
            return false;
        }

        $shipment = CpOrderShipment::where('order_id', $this->getOrderId())->firstOrNew();

        if (!$shipment->exists) {
            $shipment->ship_date = date(strtotime('-3 day', strtotime($status_date)));
            $shipment->TrackingNumber = $tracking_number;
            $shipment->save();
        }

        $this->order_date_returned = $delivery_date;
        $this->save();

        $shipped = new DeliveredEvent($this);
        $shipped->publish();

        return true;
    }

    /**
     * Getters : Retrieve and format data outside of the raw db info
     */

    /**
     * The order number is not the same as the invoice_number.  Lets subtract 2
     * @return int
     */
    public function getOrderId()
    {
        return $this->invoice_number - 2;
    }

    /**
     * Get the tracking url for the order
     * @param  boolean $short (Optional) Should we use the url shortener
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
     * @param  boolean $short (Optional) Should we use a shortner service
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
     * @param  bool $filled (Optional)  If a bool, return filled or not filled items
     * @return Collection
     */
    public function getFilledItems(bool $filled = true)
    {
        if ($filled) {
            return $this->items()->whereNotNull('rx_dispensed_id');
        } else {
            return $this->items()->whereNull('rx_dispensed_id');
        }
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
}
