<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

use GoodPill\Models\GpOrder;
use GoodPill\Models\GpPatient;
use GoodPill\Models\GpRxsSingle;
use GoodPill\Models\v2\PickListDrug;
use GoodPill\Models\Carepoint\CpFdrNdc;

require_once "helpers/helper_full_item.php";
require_once "helpers/helper_appsscripts.php";
require_once "exports/export_v2_order_items.php";

/**
 * Class GpOrderItem
 */
class GpOrderItem extends Model
{

    use \GoodPill\Traits\HasCompositePrimaryKey;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_order_items';

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
        'invoice_number'                => 'int',
        'patient_id_cp'                 => 'int',
        'rx_number'                     => 'int',
        'rx_dispensed_id'               => 'int',
        'patient_autofill_initial'      => 'int',
        'rx_autofill_initial'           => 'int',
        'zscore_initial'                => 'float',
        'refills_dispensed_default'     => 'float',
        'refills_dispensed_actual'      => 'float',
        'days_dispensed_default'        => 'int',
        'days_dispensed_actual'         => 'int',
        'qty_dispensed_default'         => 'float',
        'qty_dispensed_actual'          => 'float',
        'price_dispensed_default'       => 'float',
        'price_dispensed_actual'        => 'float',
        'qty_pended_total'              => 'float',
        'qty_pended_repacks'            => 'float',
        'count_pended_total'            => 'int',
        'count_pended_repacks'          => 'int',
        'count_lines'                   => 'int',
        'sync_to_date_days_before'      => 'float',
        'sync_to_date_days_change'      => 'float',
        'sync_to_date_max_days_default' => 'float',
        'sync_to_date_min_days_refills' => 'float',
        'sync_to_date_min_days_stock'   => 'float'
    ];

    /**
     * Fields that should be dates
     * @var array
     */
    protected $dates = [
        'item_date_added',
        'refill_date_last',
        'refill_date_manual',
        'refill_date_default'
    ];


    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'drug_name',
        'patient_id_cp',
        'groups',
        'rx_dispensed_id',
        'stock_level_initial',
        'rx_message_keys_initial',
        'patient_autofill_initial',
        'rx_autofill_initial',
        'rx_numbers_initial',
        'zscore_initial',
        'refills_dispensed_default',
        'refills_dispensed_actual',
        'days_dispensed_default',
        'days_dispensed_actual',
        'qty_dispensed_default',
        'qty_dispensed_actual',
        'price_dispensed_default',
        'price_dispensed_actual',
        'qty_pended_total',
        'qty_pended_repacks',
        'count_pended_total',
        'count_pended_repacks',
        'count_lines',
        'item_message_keys',
        'item_message_text',
        'item_type',
        'item_added_by',
        'item_date_added',
        'refill_date_last',
        'refill_date_manual',
        'refill_date_default',
        'sync_to_date_days_before',
        'sync_to_date_days_change',
        'sync_to_date_max_days_default',
        'sync_to_date_max_days_default_rxs',
        'sync_to_date_min_days_refills',
        'sync_to_date_min_days_refills_rxs',
        'sync_to_date_min_days_stock',
        'sync_to_date_min_days_stock_rxs'
    ];

    /**
     * Property to hold the picklist so it isn't constantly retrieved
     * @var PickListDrug
     */
    protected $pick_list;

    /**
     * The primary key fields
     * @var array
     */
    protected $primaryKey = ['rx_number', 'invoice_number'];

    /*
        ## RELATIONSHIPS
     */

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

    /**
     * Relationship to a patient entity
     * foreignKey - patient_id_cp
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(GpPatient::class, 'patient_id_cp');
    }

    /**
     * Relationship to a Single Rx
     * foreignKey - rx_number
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function rxs()
    {
        return $this->hasOne(GpRxsSingle::class, 'rx_number', 'rx_number');
    }

    /*
        ## CONDITIONALS
     */

    /**
     * Query v2 to see if there is already a drug pended for this order
     * @return boolean [description]
     */
    public function isPended() : bool
    {
        return ($this->getPickList()->isPended());
    }

    /**
     * Has the picklist been picked
     * @return bool True if all of the item on the picklist are picked
     */
    public function isPicked() : bool
    {
        return ($this->getPickList()->isPicked());
    }

    /*
        ## ACCESSORS
     */

    /**
     * Get access to the rx drug_name.  We use this here to rollup between brand and generic
     * @return string
     */
    public function getDrugGenericAttribute() : ?string
    {
        $rxs = $this->rxs;

        if (!$rxs->drug_generic) {
            return $rxs->drug_name;
        }

        return $rxs->drug_generic;
    }

    /**
     * Get a user friendly drug name. This is used mostly for communications to the patient
     * @return string
     */
    public function getDrugName()
    {
        $rxs = $this->rxs;

        if (!$rxs->drug_generic) {
            return $rxs->drug_name;
        }

        if (!$rxs->drug_brand) {
            return $rxs->drug_generic;
        }

        return "{$rxs->drug_generic} ({$rxs->drug_brand})";
    }

    /**
     * Get the traditional item data
     * @return array
     */
    public function getLegacyData()
    {
        if ($this->exists) {
            return load_full_item(
                ['rx_number' => $this->rx_number ],
                (new \Mysql_Wc())
            );
        }

        return null;
    }

    /**
     * Computed property to get the `refills_dispensed` field
     * @TODO - Figure out if this field can be queried directly
     * @TODO - Original function could return empty/null?
     * @return float|null
     */
    public function getRefillsDispensedAttribute() : ?float
    {
        if ($this->refills_dispensed_actual) {
            return round($this->refills_dispensed_actual, 2);
        } elseif ($this->refills_dispensed_default) {
            return round($this->refills_dispensed_default, 2);
        } elseif ($this->refills_total) {
            return round($this->refills_total, 2);
        } else {
            return null;
        }
    }


    /*
        ## PENDING RELATED
     */

    /**
     * Pend the item in V2
     * @param  string  $reason Optional. The reason we are pending the Item.
     * @param  boolean $force  Optional. Force the pend to happen even if we think
     *      it is pended in goodpill.
     * @return array
     */
    public function doPendItem(string $reason = '', bool $force = false) : ?array
    {
        $legacy_item = $this->getLegacyData();
        return v2_pend_item($legacy_item, $reason, $force);
    }

    /**
     * Unpend the item in V2
     * @param  string $reason Optional. The reason we are unpending the Item.
     * @return array
     */
    public function doUnpendItem(string $reason = '') : ?array
    {
        $legacy_item = $this->getLegacyData();
        return v2_pend_item($legacy_item, $reason);
    }

    /**
     * Query v2 to see if there is already a drug pended for this order.  If one is found, then we
     * will use it instead of getting one from v2
     * @return null|GoodPill\Models\v2\PickListDrug
     */
    public function getPickList() : ?PickListDrug
    {
        $order = $this->order;
        $pend_group = $order->pend_group;

        if (!$pend_group) {
            return null;
        }

        if (empty($this->pick_list)) {
            $this->pick_list = new PickListDrug($pend_group, $this->drug_generic);
        }

        return $this->pick_list;
    }

    /**
     * A convenient way for setting the pickelist on an item.  This is here to overcome the
     * shortcomings of v2.  V2 fails to load on certain URLS, so this allows us to pass in the
     * calculated picklist
     *
     * @param GoodPil\Models\v2\PickListDrug $pick_list A fully loaded picklist to be used for decisions.
     * @return void
     */
    public function setPickList(PickListDrug $pick_list)
    {
        $this->pick_list = $pick_list;
    }

    /**
     * Look for a matching NDC in carepoint and update the RX with the new NDC
     * @param  null|string $ndc  If null, we will attempt to get the NDC for the pended data.
     * @param  null|string $gsns If null, we will use the gsn from the pended data.  If there isn't
     *      any pended data, we will use the gsns from the rxs.
     * @return boolean True if a ndc was found and the RX was updated
     */
    public function doUpdateCpWithNdc(?string $ndc = null, ?string $gsns = null) : bool
    {
        $found_ndc = $this->searchCpNdcs($ndc, $gsns);

        // We have an ndc so lets load the RX and lets update the comment
        if ($found_ndc) {
            $cprx = CpRx::where('script_no', $this->rx_number)->first();
            if ($cprx) {
                // Get the comments to see if there is an og_ndc.
                $gpComments = new GpComments($cprx->cmt);

                // If there isn't move the current NDC to the og_ndc comment
                if (!isset($gpComments->og_ndc)) {
                    $gpComments->og_ndc = $cprx->ndc;
                    $cprx->cmt = $gpComments->toString();
                }

                // Update the current NDC
                $cprx->ndc = $found_ndc->ndc;

                // Save the CpRx
                $cprx->save();
                return true;
            }
        }

        // Load the NDC object and search to see if I can find the correct one
        // If I find one, update the RX to have the newly found NDC
        return false;
    }

    /**
     * Attempt to find a matching ndc in carepoint.  If no NDC or GSN is passed in it will Attempt
     *      to find reasonable replacements for the values.
     * @param  null|string $ndc  If null, we will attempt to get the NDC for the pended data.  If the
     *      item is not pended and $ndc is NULL we will assume there is no NDC to search for and return null.
     * @param  null|string $gsns If null, we will use the gsn from the pended data.  If there isn't
     *      any pended data, we will use the gsns from the rxs.
     * @return null|GoodPill\Models\Carepoint\CpFdrNdc
     */
    public function doSearchCpNdcs(?string $ndc = null, ?string $gsns = null) : ?CpFdrNdc
    {
        if (is_null($ndc)) {
            if (!$this->isPended()) {
                return null;
            }

            // Get the ndc or quit
            $ndc = $this->getPickList()->getNdc();

            if (is_null($ndc)) {
                return null;
            }
        }

        if (is_null($gsns)) {
            if ($this->isPended()) {
                $gsns = $this->getPickList()->getGsns();
            } else {
                $gsns = $this->rxs->drug_gsns;
            }
        }

        $gsns = explode(',', $gsns);

        return CpFdrNdc::doFindByNdcAndGsns($ndc, $gsns);
    }
}
