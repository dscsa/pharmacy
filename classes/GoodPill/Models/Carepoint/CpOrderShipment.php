<?php
namespace GoodPill\Models\Carepoint;

use Illuminate\Database\Eloquent\Model;

/**
 * Class WpUsermetum
 *
 * @property int $umeta_id
 * @property int $user_id
 * @property string|null $meta_key
 * @property string|null $meta_value
 *
 * @package App\Models
 */
class CpOrderShipment extends Model
{

    // TODO right now we are making this immutable, but we may want to come back and and make it
    // a composite key based on order_id and tracking_number.
    use \GoodPill\Traits\IsImmutable;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'CsOmShipUpdate';

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
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'invoice_nbr',
        'order_id',
        'ship_date',
        'ship_charge',
        'TrackingNumber',
        'ShipmentDate',
        'DeliveredDate'
    ];
}
