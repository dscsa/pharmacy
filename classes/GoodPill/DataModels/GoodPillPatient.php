<?php

namespace GoodPill\DataModels;

use GoodPill\Storage\Goodpill;
use GoodPill\GPModel;

use \PDO;
use \Exception;

/**
 * A class for loading Order data.  Right now we only have a few fields defined
 */
class GoodPillPatient extends GPModel
{
    /**
     * List of possible properties names for this object
     * @var array
     */
    protected $fields = [
        'patient_id_cp',
        'patient_id_wc',
        'first_name',
        'last_name',
        'birth_date',
        'patient_note',
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
        'refills_used',
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
        'patient_date_added',
        'patient_date_registered',
        'patient_date_changed',
        'patient_date_updated',
        'patient_inactive'
    ];

    /**
     * The table name to store the notifications
     * @var string
     */
    protected $table_name = "gp_patients";
}
