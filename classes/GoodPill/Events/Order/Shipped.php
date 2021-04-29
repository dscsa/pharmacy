<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\Event;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;

class Shipped extends Event
{
    /**
     * Hold the order for this event
     * @var GpOrder
     */
    protected $order;

    /**
     * Hold the order data that is used to render the messages
     * @var array
     */
    protected $order_data;

    /**
     * Make it so
     * @param GpOrder $order (Optional)  Will preset the order if passed
     */
    public function __construct(?GpOrder $order = null)
    {
        if (!is_null($order)) {
            $this->setOrder($order);
        }
    }

    /**
     * Set the order for this event
     * @param GpOrder $order The order
     */
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
                "birth_date"      => $order->patient->birth_date,
                "invoice_number"  => $order->invoice_number,
                "count_filled"    => $order->count_filled,
                "multiple_filled" => (int) ($order->count_filled > 1),
                "tracking_number" => $order->tracking_number,
                "tracking_url"    => $order->getTrackingUrl(),
                "short_tracking_url"    => $order->getTrackingUrl(true),
                "invoice_url"     => $order->getInvoiceUrl(),
                "short_invoice_url" => $order->getInvoiceUrl(true)
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


    public function getEmail() : EmailComm {

    }

    public function getSms() : SmsComm
    {

    }

    public function getSalesforce() : SalesforceComm {

    }


    public function renderEmail()
    {
        return $this->render('email.mustache');
    }

    public function renderEmailSubject()
    {
        return $this->render('email_subject.mustache');
    }


    public function renderSms()
    {
        return $this->render('sms.mustache');
    }

    /**
     * Render a template using the order data
     * @param  string $template The template name to render
     * @return string
     */
    protected function render($template)
    {
        $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

        return $m->render(
            file_get_contents("templates/Order/Shipped/". $template),
            $this->getOrderData()
        );
    }
}
