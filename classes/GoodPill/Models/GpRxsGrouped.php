<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use GoodPill\Models\GpDrugs;
use GoodPill\Models\GpPatient;
use Illuminate\Database\Eloquent\Model;
use GoodPill\Logging\GPLog;

/**
 * Class GpRxsGrouped
 *
 * @property int $patient_id_cp
 * @property string|null $drug_generic
 * @property string|null $drug_brand
 * @property string $drug_name
 * @property float|null $sig_qty_per_day
 * @property string|null $rx_message_keys
 * @property int|null $max_gsn
 * @property string|null $drug_gsns
 * @property float $refills_total
 * @property float $qty_total
 * @property int $rx_autofill
 * @property Carbon|null $refill_date_first
 * @property Carbon|null $refill_date_last
 * @property Carbon|null $refill_date_next
 * @property Carbon|null $refill_date_manual
 * @property int $best_rx_number
 * @property string $rx_numbers
 * @property string $rx_sources
 * @property Carbon|null $rx_date_changed
 * @property Carbon|null $rx_date_expired
 * @property Carbon|null $rx_date_transferred
 *
 * @package App\Models
 */
class GpRxsGrouped extends Model
{

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_rxs_grouped';

    /**
     * The primary_key for this item
     * @var null
     */
    protected $primaryKey = null;

    /**
     * Does the database contain an incrementing field?
     * @var boolean
     */
    public $incrementing = false;

    /**
     * Does the database contain timestamp fields
     * @var boolean
     */
    public $timestamps = false;

    /**
     * Fields that should be cast when they are set
     * @var array
     */
    protected $casts = [
        'patient_id_cp' => 'int',
        'sig_qty_per_day' => 'float',
        'max_gsn' => 'int',
        'refills_total' => 'float',
        'qty_total' => 'float',
        'rx_autofill' => 'int',
        'best_rx_number' => 'int',
    ];

    /**
     * Fields that should be dates when they are set
     * @var array
     */
    protected $dates = [
        'refill_date_first',
        'refill_date_last',
        'refill_date_next',
        'refill_date_manual',
        'refill_date_default',
        'rx_date_transferred',
        'rx_date_changed',
        'rx_date_expired',
        'rx_date_transferred',
    ];


    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [];

    /**
     * Checks that item has refills available
     * @return bool
     */
    public function hasRefills() : bool
    {
        return $this->refills_total > NO_REFILL;
    }

    /*
     * The following functions do not currently work
     *
     * Should these syncing functions be on a grouped entity?
     * RxsGrouped would need duplicated logic from what is on GpOrderItem
     *
     */
    /**
     * Determine by the stock level if the item is offered or not
     * @return bool
     */
    public function isNotOffered() : bool
    {
        $rxs = $this->rxs;
        $stock = $rxs->stock;

        $rx_gsn = $rxs->rx_gsn;
        $drug_name = $rxs->drug_name;
        //  This should always be set, `stock_level` isn't null in the database for any items currently
        $stock_level = $this->stock_level_initial ?: $stock->stock_level;

        GPLog::debug(
            "Stock Level for order #{$this->invoice_number}, rx #{$this->rx_number}: {$stock}",
            [
                'item' => $this->toJSON(),
                'stock_level' => $stock_level,
                'drug_name' => $drug_name,
                'rx_gsn' => $rx_gsn,
            ]
        );
        if (
            $rx_gsn > 0 ||
            $stock_level == STOCK_LEVEL['NOT OFFERED'] ||
            $stock_level == STOCK_LEVEL['ORDER DRUG']
        ) {
            return true;
        }

        return false;
    }

    /**
     * Add the item to an order if it's a new rx found
     * @return bool
     */
    public function shouldSyncToOrderNewRx() : bool
    {
        if (!$this->item_date_added)
        {
            $is_not_offered = $this->isNotOffered();
            $is_refill_only = $this->isRefillOnly();
            $is_one_time = $this->isOneTime();
            $has_refills  = $this->hasRefills();

            $eligible     = (
                $has_refills &&
                $this->rx_autofill &&
                ! $is_not_offered &&
                ! $is_refill_only &&
                ! $is_one_time &&
                ! $this->refill_date_next
            );

            return $eligible;
        }

        return false;
    }
}
