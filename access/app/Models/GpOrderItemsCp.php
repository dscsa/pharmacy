<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GpOrderItemsCp
 * 
 * @property int $invoice_number
 * @property string|null $drug_name
 * @property int $patient_id_cp
 * @property int $rx_number
 * @property int|null $rx_dispensed_id
 * @property float|null $qty_dispensed_actual
 * @property int|null $days_dispensed_actual
 * @property int $count_lines
 * @property string $item_added_by
 * @property Carbon|null $item_date_added
 *
 * @package App\Models
 */
class GpOrderItemsCp extends Model
{
	protected $table = 'gp_order_items_cp';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'invoice_number' => 'int',
		'patient_id_cp' => 'int',
		'rx_number' => 'int',
		'rx_dispensed_id' => 'int',
		'qty_dispensed_actual' => 'float',
		'days_dispensed_actual' => 'int',
		'count_lines' => 'int'
	];

	protected $dates = [
		'item_date_added'
	];

	protected $fillable = [
		'drug_name',
		'patient_id_cp',
		'rx_dispensed_id',
		'qty_dispensed_actual',
		'days_dispensed_actual',
		'count_lines',
		'item_added_by',
		'item_date_added'
	];
}
