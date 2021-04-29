<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\CalendarEvent;
use GoodPill\Models\GpOrder;

class Shipped extends CalendarEvent
{
    protected $order;

    protected $template_path = 'templates/Order/Shipped/';

    protected $order_data;

    public function __construct(?GpOrder $order = null)
    {
        if (!is_null($order)) {
            $this->setOrder($order);
        }
    }

    public function setOrder(GpOrder $order)
    {
        $this->order = $order;
    }

    /**
     * Create the json object needed for rendering the templates
     * Sample:
     *    {
     *         "first_name": "Ben",
     *         "last_name": "Brown",
     *         "invoice_number": 12345,
     *         "tracking_number": "1234123124123",
     *         "tracking_link": "http://www.gmail.com",
     *         "invoice_link": "http://www.gmail.com",
     *         "count_filled": 1,
     *         "multiple_filled": true,
     *         "filled": {
     *             "rxs": [
     *                 {"name": "drug 1"},
     *                 {"name": "drug 2"}
     *             ]
     *         },
     *         "no_fill": {
     *             "rxs": [
     *                 {"name": "no drug 1"},
     *                 {"name": "no drug 2"}
     *             ]
     *         }
     *    }
     * @return stdClass
     */
    public function getOrderData()
    {
        if (!isset($this->order_data)) {
            $order = $this->order;

            $data = [
                "first_name"      => $order->patient->first_name,
                "last_name"       => $order->patient->last_name,
                "invoice_number"  => $order->invoice_number,
                "count_filled"    => $order->count_filled,
                "multiple_filled" => (int) ($order->count_filled > 1),
                "tracking_number" => $order->tracking_number
            ];

            if ($order->count_filled > 0) {
                $rxs = [];

                $filled_items = $this->order->getItems(true);

                $filled_items->each(
                    function ($order_item) use (&$rxs) {
                        $rxs[] = ["name" => $order_item->getDrugName()];
                    }
                );

                $data['filled'] = ['rxs' => $rxs];
            }

            if ($order->count_nofill) {
                $rxs = [];

                $nofill_items = $this->order->getItems(false);

                $nofill_items->each(
                    function ($order_item) use (&$rxs) {
                        $rxs[] = ["name" => $order_item->getDrugName()];
                    }
                );

                $data['no_fill'] = ['rxs' => $rxs];
            }

            $this->order_data = $data;
        }

        return $this->order_data;
    }

    public function renderEmail()
    {
        $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

        return $m->render(
            file_get_contents($this->template_path . 'email.mustache'),
            $this->getOrderData()
        );
    }

    public function renderSms()
    {
    }

    public function renderSalesforce()
    {
    }
}
