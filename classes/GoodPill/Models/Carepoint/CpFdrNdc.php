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
class CpFdrNdc extends Model
{

    /*
        We don't want to be able to update this object
     */
    use \GoodPill\Traits\IsImmutable;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'fdrndc';

    /**
     * Using order_id as the primary key field
     * @var integer
     */
    protected $primaryKey = 'id';

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
        "active_yn"        => "int",
        "update_yn"        => "int",
        "status_cn"        => "int",
        "user_product_yn"  => "int",
        "no_prc_update_yn" => "int",
        "no_update_yn"     => "int",
        "real_product_yn"  => "int",
        "shipper"          => "int",
        "shlf_pck"         => "int",
        "csp"              => "int",
        "gcn_seqno"        => "int"
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        "ndc",
        "lblrid",
        "gcn_seqno",
        "ps",
        "df",
        "ad",
        "ln",
        "bn",
        "pndc",
        "repndc",
        "ndcfi",
        "daddnc",
        "dupdc",
        "desi",
        "desdtec",
        "desi2",
        "des2dtec",
        "dea",
        "cl",
        "gpi",
        "hosp",
        "innov",
        "ipi",
        "mini",
        "maint",
        "obc",
        "obsdtec",
        "ppi",
        "stpk",
        "repack",
        "top200",
        "ud",
        "csp",
        "color",
        "flavor",
        "shape",
        "ndl_gdge",
        "ndl_lngth",
        "syr_cpcty",
        "shlf_pck",
        "shipper",
        "skey",
        "hcfa_fda",
        "hcfa_unit",
        "hcfa_ps",
        "hcfa_appc",
        "hcfa_mrkc",
        "hcfa_trmc",
        "hcfa_typ",
        "hcfa_desc1",
        "hcfa_desi1",
        "uu",
        "pd",
        "ln25",
        "ln25i",
        "gpidc",
        "bbdc",
        "home",
        "inpcki",
        "outpcki",
        "obc_exp",
        "ps_equiv",
        "plblr",
        "hcpc",
        "top50gen",
        "obc3",
        "gmi",
        "gni",
        "gsi",
        "gti",
        "ndcgi1",
        "user_gcdf",
        "user_str",
        "real_product_yn",
        "no_update_yn",
        "no_prc_update_yn",
        "user_product_yn",
        "cpname_short",
        "status_cn",
        "update_yn",
        "active_yn",
        "ln60",
        "id"
    ];
}
