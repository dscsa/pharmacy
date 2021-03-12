<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GpOrder
 * 
 * @property int $invoice_number
 * @property int|null $patient_id_cp
 * @property int|null $patient_id_wc
 * @property int $count_items
 * @property int|null $count_filled
 * @property int|null $count_nofill
 * @property string|null $order_source
 * @property string|null $order_stage_cp
 * @property string|null $order_stage_wc
 * @property string|null $order_status
 * @property string|null $invoice_doc_id
 * @property string|null $order_address1
 * @property string|null $order_address2
 * @property string|null $order_city
 * @property string|null $order_state
 * @property string|null $order_zip
 * @property string|null $tracking_number
 * @property Carbon|null $order_date_added
 * @property Carbon|null $order_date_changed
 * @property Carbon $order_date_updated
 * @property Carbon|null $order_date_dispensed
 * @property Carbon|null $order_date_shipped
 * @property Carbon|null $order_date_returned
 * @property int|null $payment_total_default
 * @property int|null $payment_total_actual
 * @property int|null $payment_fee_default
 * @property int|null $payment_fee_actual
 * @property int|null $payment_due_default
 * @property int|null $payment_due_actual
 * @property string|null $payment_date_autopay
 * @property string|null $payment_method_actual
 * @property string|null $coupon_lines
 * @property string|null $order_note
 *
 * @package App\Models
 */
class GpOrder extends Model
{
	protected $table = 'gp_orders';
	protected $primaryKey = 'invoice_number';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'invoice_number' => 'int',
		'patient_id_cp' => 'int',
		'patient_id_wc' => 'int',
		'count_items' => 'int',
		'count_filled' => 'int',
		'count_nofill' => 'int',
		'payment_total_default' => 'int',
		'payment_total_actual' => 'int',
		'payment_fee_default' => 'int',
		'payment_fee_actual' => 'int',
		'payment_due_default' => 'int',
		'payment_due_actual' => 'int'
	];

	protected $dates = [
		'order_date_added',
		'order_date_changed',
		'order_date_updated',
		'order_date_dispensed',
		'order_date_shipped',
		'order_date_returned'
	];

	protected $fillable = [
		'patient_id_cp',
		'patient_id_wc',
		'count_items',
		'count_filled',
		'count_nofill',
		'order_source',
		'order_stage_cp',
		'order_stage_wc',
		'order_status',
		'invoice_doc_id',
		'order_address1',
		'order_address2',
		'order_city',
		'order_state',
		'order_zip',
		'tracking_number',
		'order_date_added',
		'order_date_changed',
		'order_date_updated',
		'order_date_dispensed',
		'order_date_shipped',
		'order_date_returned',
		'payment_total_default',
		'payment_total_actual',
		'payment_fee_default',
		'payment_fee_actual',
		'payment_due_default',
		'payment_due_actual',
		'payment_date_autopay',
		'payment_method_actual',
		'coupon_lines',
		'order_note'
	];
}
