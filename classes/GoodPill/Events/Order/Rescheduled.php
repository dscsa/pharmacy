<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\Order\OrderEvent;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;

class Rescheduled extends OrderEvent
{
    /**
     * The name of the event type
     * @var string
     */
    public $type = 'Order Rescheduled';

    /**
     * The path to the templates
     * @var string
     */
    protected $template_path = 'Order/Rescheduled';

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
                'Order Rescheduled',
                'Needs Form'
            ]
        );

        $patient->createEvent($this);
    }
}
