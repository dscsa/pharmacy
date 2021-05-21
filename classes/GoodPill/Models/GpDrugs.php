<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Goodpill Drugs Table
 */
class GpDrugs extends Model
{

    use \GoodPill\Traits\IsChangeable;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_drugs';

    /**
     * The primary_key for this item
     * @var string
     */
    protected $primaryKey = 'drug_generic';

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
        "drug_ordered"  => "int",
        "price30"       => "int",
        "price90"       => "int",
        "qty_repack"    => "int",
        "qty_min"       => "int",
        "days_min"      => "int",
        "max_inventory" => "int",
        "price_goodrx"  => "float",
        "price_nadac"   => "float",
        "price_retail"  => "float"
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        "drug_generic",
        "drug_brand",
        "drug_gsns",
        "drug_ordered",
        "price30",
        "price90",
        "qty_repack",
        "qty_min",
        "days_min",
        "max_inventory",
        "message_display",
        "message_verified",
        "message_destroyed",
        "price_goodrx",
        "price_nadac",
        "price_retail",
        "count_ndcs"
    ];
}
