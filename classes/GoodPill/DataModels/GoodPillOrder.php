<?php

namespace GoodPill\DataModels;

use GoodPill\Storage\Goodpill;
use GoodPill\GPModel;

use \PDO;
use \Exception;

require_once "helpers/helper_full_order.php";

/**
 * A class for loading Order data.  Right now we only have a few fields defined
 */
class GoodPillOrder extends GPModel
{
    /**
     * List of possible properties names for this object
     * @var array
     */
    protected $fields = [
        'invoice_number',
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

    private $patient;

    /**
     * The table name to store the notifications
     * @var string
     */
    protected $table_name = "gp_orders";

    /**
     * Get a patient based on the patient_id_cp
     * @return null|GoodPillPatient
     */
    public function getPatient() : ?GoodPillPatient
    {

        if ($this->patient instanceof GoodPillPatient) {
            return $this->patient;
        }

        if ($this->loaded) {
            $patient = new GoodPillPatient(['patient_id_cp' => $this->patient_id_cp]);

            if ($patient->loaded) {
                $this->patient = $patient;
                return $this->patient;
            }
        }

        return null;
    }

    /**
     * Get to old order array
     * @return null|array
     */
    public function getLegacyOrder() : ?array {
        if ($this->loaded) {
            return load_full_order(
                ['invoice_number' => $this->invoice_number ],
                (new \Mysql_Wc())

            );
        }

        return null;
    }

    /**
     * Has the order been marked as shipped
     * @return bool true if there is a shipdate
     */
    public function isShipped() : bool
    {
        return ($this->loaded && !empty($this->order_date_shipped));
    }

    /**
     * Has the order
     * @return bool [description]
     */
    public function isDispensed() : bool
    {
        return ($this->loaded && !empty($this->order_date_dispensed));
    }
}
