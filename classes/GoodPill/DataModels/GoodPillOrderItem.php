<?php

namespace GoodPill\DataModels;

use GoodPill\Storage\Goodpill;
use GoodPill\GPModel;


use \PDO;
use \Exception;

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
        'drug_name',
        'patient_id_cp',
        'rx_number',
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

    private $order;

    private $rx_single;

    /**
     * The table name to store the notifications
     * @var string
     */
    protected $table_name = "gp_order_items";

    /**
     * Get a order based on the invoice_number
     * @return null|GoodPillOrder
     */
    public function getOrder() : ?GoodPillOrder
    {

        if ($this->order instanceof GoodPillOrder) {
            return $this->order;
        }

        if ($this->loaded) {
            $order = new GoodPillOrder(['invoice_number' => $this->invoice_number]);

            if ($order->loaded) {
                $this->order = $order;
                return $this->order;
            }
        }

        return null;
    }

    /**
     * Get a Rx based on the Rx_number
     * @return null|GoodPillRxSingle
     */
    public function getRxSingle() : ?GoodPillRxSingle
    {

        if ($this->rx_single instanceof GoodPillRxSingle) {
            return $this->rx_single;
        }

        if ($this->loaded) {
            $rx_single = new GoodPillRxSingle(['rx_number' => $this->rx_number]);

            if ($rx_single->loaded) {
                $this->rx_single = $rx_single;
                return $this->rx_single;
            }
        }

        return null;
    }
}
