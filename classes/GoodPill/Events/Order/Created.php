<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\Order\OrderEvent;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;

class Created extends OrderEvent
{
    /**
     * The name of the event type
     * @var string
     */
    public $type = 'Order Created';

    /**
     * The path to the templates
     * @var string
     */
    protected $template_path = 'Order/Created';

    /**
     * Generate the Salesforce portion of the Communication event
     * Return null event because this only needs sms and email
     * @return SalesforceComm
     */
    public function getSalesforce() : ?SalesforceComm
    {
        return null;
    }

    /**
     * Publish the events
     * Cancel the any events that are not longer needed and push this event to the com calendar
     */
    public function publish() : void
    {
        $rxs = [];

        $this->order->items->each(function($item) use (&$rxs) {
            $rxs[] = [
                "name" => $item->getDrugName().' - '.$item->rxs->rx_message_text,
                "price_dispensed" => $item->price_dispensed,
                "days_dispensed" => $item->days_dispensed,
            ];
        });
        $this->order_data['ordered_items'] = $rxs;

        // Can't send notifications if the order doesn't exist
        if (!$this->order) {
            return;
        }

        //$patient = $this->order->patient;
        //$this->publishEvent();
        /*
        $patient->cancelEvents(
            [
                'Order Updated',
                'Needs Form'
            ]
        );

        $patient->createEvent($this);
        */
        $this->publishEvent();
    }
}
