<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GpStockLive extends Model
{

    /**
     * The primary_key for this item
     * @var string
     */
    protected $primaryKey = 'drug_generic';
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_stock_live';

    /**
     * Does the database contain an incrementing field
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

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
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
