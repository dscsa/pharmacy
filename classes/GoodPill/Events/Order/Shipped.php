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
            $data = [
                "first_name"      => $this->order->patient->first_name,
                "last_name"       => $this->order->patient->last_name,
                "invoice_number"  => $this->order->invoice_number,
                "count_filled"    => $this->order->count_filled,
                "multiple_filled" => (int) ($this->order->count_filled > 1),
                "tracking_number" => $this->order->tracking_number
            ];

            $filled_items = $this->order->getItems(true);
            $non_filled_items = $this->order->getItems(false);

            var_dump($filled_items);

            $this->order_data = (object) $data;
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
