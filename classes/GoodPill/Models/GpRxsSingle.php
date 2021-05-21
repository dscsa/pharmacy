<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use GoodPill\Models\GpDrugs;
use GoodPill\Models\GpPatient;
use GoodPill\Logging\GPLog;

/**
 * Class GpRxsSingle
 *
 * @property int $rx_number
 * @property int $patient_id_cp
 * @property string|null $drug_generic
 * @property string|null $drug_brand
 * @property string $drug_name
 * @property string|null $rx_message_key
 * @property string|null $rx_message_text
 * @property int $rx_gsn
 * @property string|null $drug_gsns
 * @property float $refills_left
 * @property float $refills_original
 * @property float|null $qty_left
 * @property float $qty_original
 * @property string $sig_actual
 * @property string|null $sig_initial
 * @property string|null $sig_clean
 * @property float|null $sig_qty
 * @property int|null $sig_days
 * @property float|null $sig_qty_per_day_default
 * @property float|null $sig_qty_per_day_actual
 * @property string|null $sig_durations
 * @property string|null $sig_qtys_per_time
 * @property string|null $sig_frequencies
 * @property string|null $sig_frequency_numerators
 * @property string|null $sig_frequency_denominators
 * @property int $rx_autofill
 * @property Carbon|null $refill_date_first
 * @property Carbon|null $refill_date_last
 * @property Carbon|null $refill_date_manual
 * @property Carbon|null $refill_date_default
 * @property int $rx_status
 * @property string $rx_stage
 * @property string|null $rx_source
 * @property string|null $rx_transfer
 * @property Carbon|null $rx_date_transferred
 * @property string|null $provider_npi
 * @property string|null $provider_first_name
 * @property string|null $provider_last_name
 * @property string|null $provider_clinic
 * @property string|null $provider_phone
 * @property Carbon $rx_date_changed
 * @property Carbon $rx_date_expired
 *
 * @package App\Models
 */
class GpRxsSingle extends Model
{

    use \GoodPill\Traits\IsChangeable;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_rxs_single';

    /**
     * The primary_key for this item
     * @var string
     */
    protected $primaryKey = 'rx_number';

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
        'rx_number' => 'int',
        'patient_id_cp' => 'int',
        'rx_gsn' => 'int',
        'refills_left' => 'float',
        'refills_original' => 'float',
        'qty_left' => 'float',
        'qty_original' => 'float',
        'sig_qty' => 'float',
        'sig_days' => 'int',
        'sig_qty_per_day_default' => 'float',
        'sig_qty_per_day_actual' => 'float',
        'rx_autofill' => 'int',
        'rx_status' => 'int'
    ];

    /**
     * Fields that should be dates when they are set
     * @var array
     */
    protected $dates = [
        'refill_date_first',
        'refill_date_last',
        'refill_date_manual',
        'refill_date_default',
        'rx_date_transferred',
        'rx_date_changed',
        'rx_date_expired'
    ];


    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'patient_id_cp',
        'drug_generic',
        'drug_brand',
        'drug_name',
        'rx_message_key',
        'rx_message_text',
        'rx_gsn',
        'drug_gsns',
        'refills_left',
        'refills_original',
        'qty_left',
        'qty_original',
        'sig_actual',
        'sig_initial',
        'sig_clean',
        'sig_qty',
        'sig_days',
        'sig_qty_per_day_default',
        'sig_qty_per_day_actual',
        'sig_durations',
        'sig_qtys_per_time',
        'sig_frequencies',
        'sig_frequency_numerators',
        'sig_frequency_denominators',
        'rx_autofill',
        'refill_date_first',
        'refill_date_last',
        'refill_date_manual',
        'refill_date_default',
        'rx_status',
        'rx_stage',
        'rx_source',
        'rx_transfer',
        'rx_date_transferred',
        'provider_npi',
        'provider_first_name',
        'provider_last_name',
        'provider_clinic',
        'provider_phone',
        'rx_date_changed',
        'rx_date_expired'
    ];

    /**
     * Link to the GpPatient object on the patient_id_cp
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(GpPatient::class, 'patient_id_cp', 'patient_id_cp');
    }

    /**
     * Get the drug that is associated with this RX
     * @return GpDrug|null
     */
    public function getDrugAttribute() : ?GpDrugs
    {

        if (!$this->rx_gsn) {
            return null;
        }

        return GpDrugs::where('drug_gsns', 'like', "%,{$this->rx_gsn},%")->first();
    }

    /**
     * Tries to load the drug attribute.  If it's not null then we have the drug
     * @return boolean True if we can load the drug via rx_gsn
     */
    public function isInFormulary() : bool
    {
        $drug = $this->drug;
        return (!is_null($drug));
    }

    /**
     * Does the item need to have it's drug_gsns updated
     * @return boolean True if the rx_gsn has changed or the drug_gsns field is empty
     */
    public function needsGsnUpdate() : bool
    {
        return (
            !empty($this->rx_gsn)
            && (
                ($this->hasGpChanges() && $this->hasFieldChanged('rx_gsn'))
                || empty($this->drug_gsns)
            )
        );
    }

    /**
     * Get the drug and copy the fields to the RX object
     * @return boolean True if the data was updated, False if the drug isn't in v2
     */
    public function updateDrugGsns() : bool
    {
        if (!$this->isInFormulary()) {
            return false;
        }

        $drug = $this->drug;

        if ($drug) {
            GPLog::warning(
                "GpRxSingle::updateDrugGsns() drug found and updating",
                [
                    "rx_number"     => $this->rx_number,
                    "patient_id_cp" => $this->patient_id_cp
                ]
            );

            $this->drug_generic = $drug->drug_generic;
            $this->drug_brand   = $drug->drug_brand;
            $this->drug_gsns    = $drug->drug_gsns;
            $this->save();
        } else {
            GPLog::warning(
                "GpRxSingle::updateDrugGsns() drug not found",
                [
                    "rx_number"     => $this->rx_number,
                    "patient_id_cp" => $this->patient_id_cp,
                    "rx_gsn"        => $this->rx_gsn
                ]
            );
        }

        // If we don't need to update the drug_gsns then the operatino was a success
        return (!$rx_single->needsGsnUpdate());
    }


}
