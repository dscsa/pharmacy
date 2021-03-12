<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GpRxsSingleCp
 * 
 * @property int $rx_number
 * @property int $patient_id_cp
 * @property string $drug_name
 * @property string|null $rx_message_key
 * @property int $rx_gsn
 * @property float $refills_left
 * @property float $refills_original
 * @property float|null $qty_left
 * @property float $qty_original
 * @property string $sig_actual
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
class GpRxsSingleCp extends Model
{
	protected $table = 'gp_rxs_single_cp';
	protected $primaryKey = 'rx_number';
	public $incrementing = false;
	public $timestamps = false;

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

	protected $dates = [
		'refill_date_first',
		'refill_date_last',
		'refill_date_manual',
		'refill_date_default',
		'rx_date_transferred',
		'rx_date_changed',
		'rx_date_expired'
	];

	protected $fillable = [
		'patient_id_cp',
		'drug_name',
		'rx_message_key',
		'rx_gsn',
		'refills_left',
		'refills_original',
		'qty_left',
		'qty_original',
		'sig_actual',
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
}
