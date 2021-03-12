<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GpStockByMonth
 * 
 * @property string $drug_generic
 * @property Carbon $month
 * @property string|null $stock_level
 * @property string|null $drug_brand
 * @property string|null $drug_gsns
 * @property string|null $message_display
 * @property float|null $price_per_month
 * @property int|null $drug_ordered
 * @property int|null $qty_repack
 * @property string|null $months_inventory
 * @property float|null $avg_inventory
 * @property float|null $last_inventory
 * @property string|null $months_entered
 * @property float|null $stddev_entered
 * @property float|null $total_entered
 * @property string|null $months_dispensed
 * @property float|null $stddev_dispensed_actual
 * @property float|null $total_dispensed_actual
 * @property float|null $total_dispensed_default
 * @property float|null $stddev_dispensed_default
 * @property float|null $zlow_threshold
 * @property float|null $zhigh_threshold
 * @property float|null $zscore
 * @property float $inventory_sum
 * @property float $inventory_count
 * @property float $inventory_min
 * @property float $inventory_max
 * @property float $inventory_sumsqr
 * @property float $entered_sum
 * @property float $entered_count
 * @property float $entered_min
 * @property float $entered_max
 * @property float $entered_sumsqr
 * @property float $verified_sum
 * @property float $verified_count
 * @property float $verified_min
 * @property float $verified_max
 * @property float $verified_sumsqr
 * @property float $refused_sum
 * @property float $refused_count
 * @property float $refused_min
 * @property float $refused_max
 * @property float $refused_sumsqr
 * @property float $expired_sum
 * @property float $expired_count
 * @property float $expired_min
 * @property float $expired_max
 * @property float $expired_sumsqr
 * @property float $disposed_sum
 * @property float $disposed_count
 * @property float $disposed_min
 * @property float $disposed_max
 * @property float $disposed_sumsqr
 * @property float $dispensed_sum
 * @property float $dispensed_count
 * @property float $dispensed_min
 * @property float $dispensed_max
 * @property float $dispensed_sumsqr
 *
 * @package App\Models
 */
class GpStockByMonth extends Model
{
	protected $table = 'gp_stock_by_month';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'price_per_month' => 'float',
		'drug_ordered' => 'int',
		'qty_repack' => 'int',
		'avg_inventory' => 'float',
		'last_inventory' => 'float',
		'stddev_entered' => 'float',
		'total_entered' => 'float',
		'stddev_dispensed_actual' => 'float',
		'total_dispensed_actual' => 'float',
		'total_dispensed_default' => 'float',
		'stddev_dispensed_default' => 'float',
		'zlow_threshold' => 'float',
		'zhigh_threshold' => 'float',
		'zscore' => 'float',
		'inventory_sum' => 'float',
		'inventory_count' => 'float',
		'inventory_min' => 'float',
		'inventory_max' => 'float',
		'inventory_sumsqr' => 'float',
		'entered_sum' => 'float',
		'entered_count' => 'float',
		'entered_min' => 'float',
		'entered_max' => 'float',
		'entered_sumsqr' => 'float',
		'verified_sum' => 'float',
		'verified_count' => 'float',
		'verified_min' => 'float',
		'verified_max' => 'float',
		'verified_sumsqr' => 'float',
		'refused_sum' => 'float',
		'refused_count' => 'float',
		'refused_min' => 'float',
		'refused_max' => 'float',
		'refused_sumsqr' => 'float',
		'expired_sum' => 'float',
		'expired_count' => 'float',
		'expired_min' => 'float',
		'expired_max' => 'float',
		'expired_sumsqr' => 'float',
		'disposed_sum' => 'float',
		'disposed_count' => 'float',
		'disposed_min' => 'float',
		'disposed_max' => 'float',
		'disposed_sumsqr' => 'float',
		'dispensed_sum' => 'float',
		'dispensed_count' => 'float',
		'dispensed_min' => 'float',
		'dispensed_max' => 'float',
		'dispensed_sumsqr' => 'float'
	];

	protected $dates = [
		'month'
	];

	protected $fillable = [
		'stock_level',
		'drug_brand',
		'drug_gsns',
		'message_display',
		'price_per_month',
		'drug_ordered',
		'qty_repack',
		'months_inventory',
		'avg_inventory',
		'last_inventory',
		'months_entered',
		'stddev_entered',
		'total_entered',
		'months_dispensed',
		'stddev_dispensed_actual',
		'total_dispensed_actual',
		'total_dispensed_default',
		'stddev_dispensed_default',
		'zlow_threshold',
		'zhigh_threshold',
		'zscore',
		'inventory_sum',
		'inventory_count',
		'inventory_min',
		'inventory_max',
		'inventory_sumsqr',
		'entered_sum',
		'entered_count',
		'entered_min',
		'entered_max',
		'entered_sumsqr',
		'verified_sum',
		'verified_count',
		'verified_min',
		'verified_max',
		'verified_sumsqr',
		'refused_sum',
		'refused_count',
		'refused_min',
		'refused_max',
		'refused_sumsqr',
		'expired_sum',
		'expired_count',
		'expired_min',
		'expired_max',
		'expired_sumsqr',
		'disposed_sum',
		'disposed_count',
		'disposed_min',
		'disposed_max',
		'disposed_sumsqr',
		'dispensed_sum',
		'dispensed_count',
		'dispensed_min',
		'dispensed_max',
		'dispensed_sumsqr'
	];
}
