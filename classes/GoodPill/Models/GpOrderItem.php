<?php

/**
 * Created by Reliese Model.
 */

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

use GoodPill\Models\GpOrder;
use GoodPill\Models\GpPatient;
use GoodPill\Models\GpRxsSingle;

/**
 * Class GpOrderItem
 *
 * @property int $invoice_number
 * @property string $drug_name
 * @property int $patient_id_cp
 * @property int $rx_number
 * @property string|null $groups
 * @property int|null $rx_dispensed_id
 * @property string|null $stock_level_initial
 * @property string|null $rx_message_keys_initial
 * @property int|null $patient_autofill_initial
 * @property int|null $rx_autofill_initial
 * @property string|null $rx_numbers_initial
 * @property float|null $zscore_initial
 * @property float|null $refills_dispensed_default
 * @property float|null $refills_dispensed_actual
 * @property int|null $days_dispensed_default
 * @property int|null $days_dispensed_actual
 * @property float|null $qty_dispensed_default
 * @property float|null $qty_dispensed_actual
 * @property float|null $price_dispensed_default
 * @property float|null $price_dispensed_actual
 * @property float|null $qty_pended_total
 * @property float|null $qty_pended_repacks
 * @property int|null $count_pended_total
 * @property int|null $count_pended_repacks
 * @property int $count_lines
 * @property string|null $item_message_keys
 * @property string|null $item_message_text
 * @property string|null $item_type
 * @property string $item_added_by
 * @property Carbon|null $item_date_added
 * @property Carbon|null $refill_date_last
 * @property Carbon|null $refill_date_manual
 * @property Carbon|null $refill_date_default
 * @property float|null $sync_to_date_days_before
 * @property float|null $sync_to_date_days_change
 * @property float|null $sync_to_date_max_days_default
 * @property string|null $sync_to_date_max_days_default_rxs
 * @property float|null $sync_to_date_min_days_refills
 * @property string|null $sync_to_date_min_days_refills_rxs
 * @property float|null $sync_to_date_min_days_stock
 * @property string|null $sync_to_date_min_days_stock_rxs
 *
 * @package App\Models
 */
class GpOrderItem extends Model
{
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_order_items';

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
        'invoice_number'                => 'int',
        'patient_id_cp'                 => 'int',
        'rx_number'                     => 'int',
        'rx_dispensed_id'               => 'int',
        'patient_autofill_initial'      => 'int',
        'rx_autofill_initial'           => 'int',
        'zscore_initial'                => 'float',
        'refills_dispensed_default'     => 'float',
        'refills_dispensed_actual'      => 'float',
        'days_dispensed_default'        => 'int',
        'days_dispensed_actual'         => 'int',
        'qty_dispensed_default'         => 'float',
        'qty_dispensed_actual'          => 'float',
        'price_dispensed_default'       => 'float',
        'price_dispensed_actual'        => 'float',
        'qty_pended_total'              => 'float',
        'qty_pended_repacks'            => 'float',
        'count_pended_total'            => 'int',
        'count_pended_repacks'          => 'int',
        'count_lines'                   => 'int',
        'sync_to_date_days_before'      => 'float',
        'sync_to_date_days_change'      => 'float',
        'sync_to_date_max_days_default' => 'float',
        'sync_to_date_min_days_refills' => 'float',
        'sync_to_date_min_days_stock'   => 'float'
    ];

    /**
     * Fields that should be dates
     * @var array
     */
    protected $dates = [
        'item_date_added',
        'refill_date_last',
        'refill_date_manual',
        'refill_date_default'
    ];


    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'drug_name',
        'patient_id_cp',
        'groups',
        'rx_dispensed_id',
        'stock_level_initial',
        'rx_message_keys_initial',
        'patient_autofill_initial',
        'rx_autofill_initial',
        'rx_numbers_initial',
        'zscore_initial',
        'refills_dispensed_default',
        'refills_dispensed_actual',
        'days_dispensed_default',
        'days_dispensed_actual',
        'qty_dispensed_default',
        'qty_dispensed_actual',
        'price_dispensed_default',
        'price_dispensed_actual',
        'qty_pended_total',
        'qty_pended_repacks',
        'count_pended_total',
        'count_pended_repacks',
        'count_lines',
        'item_message_keys',
        'item_message_text',
        'item_type',
        'item_added_by',
        'item_date_added',
        'refill_date_last',
        'refill_date_manual',
        'refill_date_default',
        'sync_to_date_days_before',
        'sync_to_date_days_change',
        'sync_to_date_max_days_default',
        'sync_to_date_max_days_default_rxs',
        'sync_to_date_min_days_refills',
        'sync_to_date_min_days_refills_rxs',
        'sync_to_date_min_days_stock',
        'sync_to_date_min_days_stock_rxs'
    ];

    /**
     * Relationship to an order entity
     * foreignKey - invoice_number
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(GpOrder::class, 'invoice_number');
    }

    /**
     * Relationship to a patient entity
     * foreignKey - patient_id_cp
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(GpPatient::class, 'patient_id_cp');
    }

    /**
     * Relationship to a Single Rx
     * foreignKey - rx_number
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function rxs()
    {
        return $this->hasOne(GpRxsSingle::class, 'rx_number', 'rx_number');
    }

    public function getDrugName()
    {
        $rxs = $this->rxs;

        if (!$rxs->drug_generic) {
            return $rxs->drug_name;
        }

        if (!$rxs->drug_brand) {
            return $rxs->drug_generic;
        }

        return "{$rxs->drug_generic} ({$rxs->drug_brand})";
    }
}
