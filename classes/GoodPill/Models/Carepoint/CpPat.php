<?php

namespace GoodPill\Models\Carepoint;

use Illuminate\Database\Eloquent\Model;
use GoodPill\Utilities\GpComments;

/**
 * Class CpRX
 */
class CpPat extends Model
{

    use \GoodPill\Traits\IsNotCreatable;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'cppat';

    /**
     * Using order_id as the primary key field
     * @var integer
     */
    protected $primaryKey = 'pat_id';

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
        "add_user_id"            => "int",
        "anonymous_yn"           => "int",
        "app_flags"              => "int",
        "auto_refill_cn"         => "int",
        "bad_check_yn"           => "int",
        "chg_user_id"            => "int",
        "cmt_id"                 => "int",
        "comp_cn"                => "int",
        "do_not_resuscitate_yn"  => "int",
        "edu_level_cn"           => "int",
        "facility_pat_yn"        => "int",
        "fam_pat_id"             => "int",
        "fam_relation_cn"        => "int",
        "fill_stop_reason_cn"    => "int",
        "fill_stop_user_id"      => "int",
        "fill_yn"                => "int",
        "generics_yn"            => "int",
        "hh_pat_id"              => "int",
        "hh_relation_cn"         => "int",
        "hp_blnAdmExt"           => "int",
        "hp_ExtAtt"              => "int",
        "label_style_cn"         => "int",
        "market_yn"              => "int",
        "medsync_cn"             => "int",
        "no_safety_caps_yn"      => "int",
        "paperless_yn"           => "int",
        "pat_id"                 => "int",
        "pat_loc_cn"             => "int",
        "pat_status_cn"          => "int",
        "pat_type_cn"            => "int",
        "pref_meth_cont_cn"      => "int",
        "price_formula_id"       => "int",
        "primary_md_id"          => "int",
        "primary_store_id"       => "int",
        "rec_release_status_cn"  => "int",
        "religion_cn"            => "int",
        "residence_cn"           => "int",
        "resp_party_id"          => "int",
        "rx_priority_default_cn" => "int",
        "sc_pat_id"              => "int",
        "secondary_md_id"        => "int",
        "ship_cn"                => "int",
        "status_cn"              => "int"
    ];

    /**
     * fields that should be cast to date formats
     * @var array
     */
    protected $dates = [
        "add_date",
        "birth_date",
        "chg_date",
        "death_date",
        "discharge_date",
        "discharge_exp_date",
        "fill_resume_date",
        "fill_stop_date",
        "rec_release_date",
        "registration_date",
        "timestmp"
    ];


    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        "add_date",
        "add_user_id",
        "alias",
        "alt1_id",
        "anonymous_yn",
        "app_flags",
        "auto_refill_cn",
        "bad_check_yn",
        "best_cont_time",
        "birth_date",
        "chart_id",
        "chg_date",
        "chg_user_id",
        "cmt_id",
        "cmt",
        "comp_cn",
        "conv_code",
        "death_date",
        "discharge_date",
        "discharge_exp_date",
        "dln_state_cd",
        "dln",
        "do_not_resuscitate_yn",
        "edu_level_cn",
        "email",
        "ethnicity_cd",
        "facility_pat_yn",
        "fam_pat_id",
        "fam_relation_cn",
        "fill_resume_date",
        "fill_stop_date",
        "fill_stop_reason_cn",
        "fill_stop_user_id",
        "fill_yn",
        "fname_sdx",
        "fname",
        "gender_cd",
        "generics_yn",
        "hh_pat_id",
        "hh_relation_cn",
        "hp_blnAdmExt",
        "hp_ExtAtt",
        "label_style_cn",
        "lname_sdx",
        "lname",
        "marital_status_cd",
        "market_yn",
        "medsync_cn",
        "mmname",
        "mname",
        "MRN_ID",
        "name_spouse",
        "nh_pat_id",
        "no_safety_caps_yn",
        "paperless_yn",
        "pat_id",
        "pat_loc_cn",
        "pat_status_cn",
        "pat_type_cn",
        "pref_meth_cont_cn",
        "price_formula_id",
        "primary_lang_cd",
        "primary_md_id",
        "primary_store_id",
        "rec_release_date",
        "rec_release_status_cn",
        "registration_date",
        "religion_cn",
        "representative",
        "residence_cn",
        "resp_party_id",
        "rx_priority_default_cn",
        "sc_pat_id",
        "secondary_md_id",
        "ship_cn",
        "ssn",
        "status_cn",
        "suffix_lu",
        "timestmp",
        "title_lu",
        "user_def_1",
        "user_def_2",
        "user_def_3",
        "user_def_4"
    ];

    /**
     * Parse the comments from the patient and extract any gpComments
     * @return GoodPill\Utilities\GpComments
     */
    public function getGpComments() : GpComments
    {
        return new GpComments($this->cmt);
    }

    /**
     * Use a GpComments object to update the comments on the patien
     * @param GoodPill\Utilities\GpComments $comments [description]
     */
    public function setGpComments(GpComments $comments) : void
    {
        $this->cmt = $comments->toString();
        $this->save();
    }

}
