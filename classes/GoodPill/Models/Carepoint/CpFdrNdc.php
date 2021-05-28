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

    /**
     * Look for a Carepoint NDC based on the GSN and NDC passed
     * @param  string $ndc  This will most frequently be an ndc seperated by '-'.
     * @param  array  $gsns This should be an array of the possible GCNs(GSNs)
     * @return null|CpFdrNdc  It will be the first one that matches
     */
    public static function doFindByNdcAndGsns(string $ndc, array $gsns) : ?CpFdrNdc
    {
        $ndcs_to_test = [];
        // If se don't have enough parts we should leave
        if (count($ndc_parts) >= 2) {
            return null;
        }


        /*
            Get the various NDC's we want to use in the query
         */
        if (strpos('-', $ndc) !== false) {
            $ndc_parts = explode('-', $ndc);
            $ndc_parts[0] = str_pad($ndc_parts[0], 5, '0', STR_PAD_LEFT);
            $ndc_parts[1] = str_pad($ndc_parts[1], 4, '0', STR_PAD_LEFT);

            // Striaght Proper padding
            $ndcs_to_test[] = str_pad($ndc_parts[0], 5, '0', STR_PAD_LEFT)
                            . str_pad($ndc_parts[1], 4, '0', STR_PAD_LEFT)
                            . '%';

            // Failing that try padding the first and adding a 0 to the front and shifting the 5th
            // digit off the middle 4 + gcn.  If that is a match, update
            $ndcs_to_test[] = str_pad($ndc_parts[0], 5, '0', STR_PAD_LEFT)
                            . substr(
                                str_pad($ndc_parts[1], 5, '0', STR_PAD_RIGHT),
                                0,
                                4
                            )
                            . '%';

            // Failing that try the first 5 padded + the gcn.  Take the matches and loop through to see
            // if we can find an appropriate match based on the items found.
            $ndcs_to_test[] = str_pad($ndc_parts[0], 5, '0', STR_PAD_LEFT)
                            . '%';
        } else {
            $ndcs_to_test[] = $ndc;
            $ndcs_to_test[] = $ndc . '%';
        }

        /*
            Loop through all the possible NDCS until we find a possible match
         */

        foreach ($ndcs_to_test as $test_ndc) {
            $possible_ndcs = CpFdrNdc::where('ndc', 'like', $test_ndc)
                ->whereIn('gcn_seqno', $gsns)
                ->orderBy('ndc', 'asc')
                ->get();

            // We've found some NDC's so lets just grab the first one
            if ($possible_ndcs->count() > 0) {
                return $possible_ndcs->first();
            }
        }

        return null;
    }
}
