<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GpStockLive
 *
 * @property float|null $avg_inventory
 * @property float|null $last_inv_high_threshold
 * @property float|null $last_inv_low_threshold
 * @property float|null $last_inventory
 * @property float|null $price_per_month
 * @property float|null $stddev_dispensed_actual
 * @property float|null $stddev_dispensed_default
 * @property float|null $stddev_entered
 * @property float|null $total_dispensed_actual
 * @property float|null $total_dispensed_default
 * @property float|null $total_entered
 * @property float|null $zhigh_threshold
 * @property float|null $zlow_threshold
 * @property float|null $zscore
 * @property int|null $drug_ordered
 * @property int|null $qty_repack
 * @property string $drug_generic
 * @property string|null $drug_brand
 * @property string|null $drug_gsns
 * @property string|null $message_display
 * @property string|null $months_dispensed
 * @property string|null $months_entered
 * @property string|null $months_inventory
 * @property string|null $stock_level
 *
 * @package App\Models
 */
class GpStockLive extends Model
{

    protected $primaryKey = 'drug_generic';
    protected $table = 'gp_stock_live';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'avg_inventory' => 'float',
        'drug_ordered' => 'int',
        'last_inventory' => 'float',
        'last_inv_high_threshold' => 'float',
        'last_inv_low_threshold' => 'float',
        'price_per_month' => 'float',
        'qty_repack' => 'int',
        'stddev_dispensed_actual' => 'float',
        'stddev_dispensed_default' => 'float',
        'stddev_entered' => 'float',
        'total_dispensed_actual' => 'float',
        'total_dispensed_default' => 'float',
        'total_entered' => 'float',
        'zhigh_threshold' => 'float',
        'zlow_threshold' => 'float',
        'zscore' => 'float',
    ];

    protected $fillable = [
        'avg_inventory',
        'last_inv_high_threshold',
        'last_inv_low_threshold',
        'last_inventory',
        'price_per_month',
        'stddev_dispensed_actual',
        'stddev_dispensed_default',
        'stddev_entered',
        'total_dispensed_actual',
        'total_dispensed_default',
        'total_entered',
        'zhigh_threshold',
        'zlow_threshold',
        'zscore',
        'drug_ordered',
        'qty_repack',
        'drug_brand',
        'drug_gsns',
        'message_display',
        'months_dispensed',
        'months_entered',
        'months_inventory',
        'stock_level'
    ];

}
