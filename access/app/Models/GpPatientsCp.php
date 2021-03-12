<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GpPatientsCp
 * 
 * @property int $patient_id_cp
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $birth_date
 * @property string|null $patient_note
 * @property string|null $phone1
 * @property string|null $phone2
 * @property string|null $email
 * @property int|null $patient_autofill
 * @property string|null $pharmacy_name
 * @property string|null $pharmacy_npi
 * @property string|null $pharmacy_fax
 * @property string|null $pharmacy_phone
 * @property string|null $pharmacy_address
 * @property string|null $payment_card_type
 * @property string|null $payment_card_last4
 * @property Carbon|null $payment_card_date_expired
 * @property string|null $payment_method_default
 * @property string|null $payment_coupon
 * @property string|null $tracking_coupon
 * @property string|null $patient_address1
 * @property string|null $patient_address2
 * @property string|null $patient_city
 * @property string|null $patient_state
 * @property string|null $patient_zip
 * @property float|null $refills_used
 * @property string $language
 * @property string|null $allergies_none
 * @property string|null $allergies_cephalosporins
 * @property string|null $allergies_sulfa
 * @property string|null $allergies_aspirin
 * @property string|null $allergies_penicillin
 * @property string|null $allergies_erythromycin
 * @property string|null $allergies_codeine
 * @property string|null $allergies_nsaids
 * @property string|null $allergies_salicylates
 * @property string|null $allergies_azithromycin
 * @property string|null $allergies_amoxicillin
 * @property string|null $allergies_tetracycline
 * @property string|null $allergies_other
 * @property string|null $medications_other
 * @property Carbon $patient_date_added
 * @property Carbon|null $patient_date_changed
 * @property Carbon $patient_date_updated
 * @property string|null $patient_inactive
 *
 * @package App\Models
 */
class GpPatientsCp extends Model
{
	protected $table = 'gp_patients_cp';
	protected $primaryKey = 'patient_id_cp';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'patient_id_cp' => 'int',
		'patient_autofill' => 'int',
		'refills_used' => 'float'
	];

	protected $dates = [
		'birth_date',
		'payment_card_date_expired',
		'patient_date_added',
		'patient_date_changed',
		'patient_date_updated'
	];

	protected $fillable = [
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
		'patient_date_changed',
		'patient_date_updated',
		'patient_inactive'
	];
}
