<?php

namespace GoodPill\Models\Carepoint;

use Illuminate\Database\Eloquent\Model;
use GoodPill\Models\GpRxsSingle;

/**
 * Class CpRX
 */
class CpRx extends Model
{

    use \GoodPill\Traits\IsNotCreatable;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'cprx';

    /**
     * Using order_id as the primary key field
     * @var integer
     */
    protected $primaryKey = 'rx_id';

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

    /**
     * fields that should be cast to date formats
     * @var array
     */
    protected $dates = [
        "effective_date",
        "autofill_resume_date",
        "orig_expire_date",
        "timestmp",
        "chg_date",
        "add_date",
        "orig_date",
        "orig_disp_date",
        "injury_date",
        "life_date",
        "sched_time",
        "sched_date",
        "refill_date",
        "last_refill_date",
        "stop_date",
        "expire_date",
        "start_date"
    ];


    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'rx_id',
        'script_no',
        'old_script_no',
        'pat_id',
        'store_id',
        'md_id',
        'ndc',
        'gcn_seqno',
        'mfg',
        'drug_name',
        'input_src_cn',
        'src_pat_meds_cn',
        'start_date',
        'expire_date',
        'stop_date',
        'sig_code',
        'sig_text',
        'written_qty',
        'starter_qty',
        'days_supply',
        'pkg_size',
        'units_per_dose',
        'units_entered',
        'awp',
        'udef',
        'ful',
        'mac',
        'aac',
        'freq_entered',
        'freq_of_admin',
        'sched_of_admin_cn',
        'daw_yn',
        'refills_orig',
        'refills_left',
        'last_rxdisp_id',
        'last_refill_qty',
        'last_refill_date',
        'refill_date',
        'src_org_id',
        'cmt',
        'exit_state_cn',
        'script_status_cn',
        'sched_date',
        'sched_time',
        'scheduled_yn',
        'drug_dea_class',
        'manual_add_yn',
        'status_cn',
        'life_date',
        'self_prescribed_yn',
        'last_transfer_type_io',
        'last_disp_prod',
        'transfer_cnt',
        'wc_claim_id',
        'injury_date',
        'gpi_rx',
        'auth_by',
        'orig_disp_date',
        'short_term_yn',
        'orig_date',
        'refills_used',
        'wc_emp_id',
        'dose_unit',
        'dosage_multiplier',
        'df',
        'uu',
        'add_date',
        'add_user_id',
        'chg_date',
        'chg_user_id',
        'app_flags',
        'timestmp',
        'sched2_no',
        'orig_expire_date',
        'rxq_status_cn',
        'IVRCmt',
        'wc_bodypart',
        'comp_ndc',
        'treatment_yn',
        'ivr_callback',
        'autofill_yn',
        'autofill_resume_date',
        'workflow_status_cn',
        'extern_process_cn',
        'md_fac_id',
        'owner_store_id',
        'study_id',
        'min_days_until_refill',
        'sig_text_english',
        'order_fullfillment_cn',
        'taxable',
        'MAR_flag',
        'FreeFormStrength',
        'priority_cn',
        'ecs_yn',
        'edit_locked_yn',
        'locked_yn',
        'locked_id',
        'locked_user_id',
        'daw_rx_cn',
        'ctrl_serial_no',
        'effective_date',
        'promo_cn',
        'csr_treatment_type_cn'
    ];


    /**
     * Find the gpRxSingle for this CpRx
     * @return null|GoodPill\Models\GpRxsSingle
     */
    public function getGpRxsSingle() {
        return GpRxsSingle::where('rx_number', $this->script_no);
    }
}
