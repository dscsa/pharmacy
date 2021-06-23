<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use GoodPill\Models\GpOrder;

/**
 * Class GpPendGroup
 *
 */
class GpPendGroup extends Model
{
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_pend_group';

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
        'invoice_number' => 'int',
    ];

    /**
     * Fields that should be dates when they are set
     * @var array
     */
    protected $dates = [
        "initial_pend_date",
        "last_pend_date"
    ];


    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        "invoice_number",
        "pend_group",
        "initial_pend_date",
        "last_pend_date"
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
}
