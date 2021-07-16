<?php
namespace GoodPill\Models\Carepoint;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CsomLine
 *
 * Stores the association between an order and an rx
 *
 */
class CsomLine extends Model
{
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'csomline';

    /**
     * Using order_id as the primary key field
     * @var integer
     */
    protected $primaryKey = 'line_id';

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
        'line_id' => 'int',
        'order_id' => 'int',
        'rx_id' => 'int',
        'rxdisp_id' => 'int',
        'line_state_cn' => 'int',
        'line_status_cn' => 'int',
        'hold_yn' => 'int',
        'add_user_id' => 'int',
        'chg_user_id' => 'int'
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'line_id',
        'order_id',
        'rx_id',
        'rxdisp_id',
        'line_state_cn',
        'line_status_cn',
        'hold_yn',
        'add_user_id',
        'add_date',
        'chg_user_id',
        'chg_date'
    ];

    /**
     * Link to the GpPatient object on the patient_id_cp
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function cprx()
    {
        return $this->hasOne(CpRx::class, 'rx_id', 'rx_id');
    }

    /**
     * Override the default delete function so we can also update the recored that is created by
     * the mssql delete trigger
     * @return bool|null
     */
    public function delete() : ?bool
    {
        // Have to delete fist so the trigger will fire
        $return = parent::delete();

        // Grab the deleted item and update it so that the change user is our automation.
        $deleted = CsomLine_Deleted::where('line_id', $this->line_id)->first();

        if ($deleted) {
            $deleted->chg_user_id = CAREPOINT_AUTOMATION_USER;
            $deleted->save();
        }

        return $return;
    }
}
