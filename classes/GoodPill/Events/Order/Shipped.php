<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\Order\OrderEvent;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;

class Shipped extends OrderEvent
{
    /**
     * The name of the event type
     * @var string
     */
    public $type = 'Order Shipped';

    /**
     * The path to the templates
     * @var string
     */
    protected $template_path = 'Order/Shipped';

    /**
     * Publish the events
     * Cancel the any events that are not longer needed and push this event to the com calendar
     */
    public function publish() : void
    {
        // Can't send notfications if the order doesn't exist
        if (!$this->order) {
            return;
        }

        $patient = $this->order->patient;

        $patient->cancelEvents(
            [
                'Order Shipped',
                'Order Dispensed',
                'Order Cancelled',
                'Needs Form'
            ]
        );

        $patient->createEvent($this);
    }
}
