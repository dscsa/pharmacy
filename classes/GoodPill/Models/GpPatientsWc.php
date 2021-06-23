<?php

namespace GoodPill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GpPatientsWc
 */
class GpPatientsWc extends Model
{
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_patients_wc';

    /**
     * The primary_key for this item
     * @var string
     */
    protected $primaryKey = 'patient_id_wc';

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
        'patient_id_cp' => 'int',
        'patient_id_wc' => 'int',
        'patient_autofill' => 'int'
    ];

    /**
     * Fields that hold dates
     * @var array
     */
    protected $dates = [
        'birth_date',
        'payment_card_date_expired',
        'patient_date_registered',
        'patient_date_updated'
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'patient_id_cp',
        'first_name',
        'last_name',
        'birth_date',
        'phone1',
        'phone2',
        'email',
        'patient_autofill',
        'pharmacy_name',
        'pharmacy_npi',
        'pharmacy_fax',
        'pharmacy_phone',
        'pharmacy_address',
        'payment_card_type',
        'payment_card_last4',
        'payment_card_date_expired',
        'payment_method_default',
        'payment_coupon',
        'tracking_coupon',
        'patient_address1',
        'patient_address2',
        'patient_city',
        'patient_state',
        'patient_zip',
        'language',
        'allergies_none',
        'allergies_cephalosporins',
        'allergies_sulfa',
        'allergies_aspirin',
        'allergies_penicillin',
        'allergies_erythromycin',
        'allergies_codeine',
        'allergies_nsaids',
        'allergies_salicylates',
        'allergies_azithromycin',
        'allergies_amoxicillin',
        'allergies_tetracycline',
        'allergies_other',
        'medications_other',
        'patient_date_registered',
        'patient_date_updated',
        'patient_inactive'
    ];

    /**
     * print the patient label.
     * @return string
     */
    public function getPatientLabel()
    {
        return sprintf(
            "%s %s %s",
            $this->first_name,
            $this->last_name,
            $this->birth_date
        );
    }
}
