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
}
