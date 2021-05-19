<?php
namespace GoodPill\Models\Carepoint;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CpCsomShip
 *
 * Stores the shipping record for an order.  Don't know why its seperate from
 * the ShipUpdate table, but it is.
 *
 */
class CpCsomShip extends Model
{
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'csom_ship';

    /**
     * Using order_id as the primary key field
     * @var integer
     */
    protected $primaryKey = 'order_id';

    /**
     * [protected description]
     * @var string
     */
    protected $connection= 'carepoint';

    /**
     * Does the database contining timestamp fields
     * @var boolean
     */
    public $timestamps = false;

    /**
     * Does the database contining an incrementing field?
     * @var boolean
     */
    public $incrementing = false;

    /**
     * Fields that should be cast when they are set
     * @var array
     */
    protected $casts = [
        'order_id' => 'int',
        'processing_flag' => 'int'
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'order_id',
        'processing_flag',
        'tracking_code',
        'Bill_Name',
        'bill_addr1',
        'bill_addr2',
        'bill_addr3',
        'bill_city',
        'bill_state_cd',
        'bill_zip',
        'bill_country_cd',
        'ship_name',
        'ship_addr1',
        'ship_addr2',
        'ship_addr3',
        'ship_city',
        'ship_state_cd',
        'ship_zip',
        'ship_country_cd',
        'ship_phone',
        'ship_email'
    ];
}
