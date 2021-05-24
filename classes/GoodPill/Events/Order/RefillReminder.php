<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\Order\OrderEvent;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;

class RefillReminder extends OrderEvent
{
    /**
     * The name of the event type
     * @var string
     */
    public $type = 'Refill Reminder';

    /**
     * The path to the templates
     * @var string
     */
    protected $template_path = 'Order/Refill_reminder';

    /**
     * RefillReminder constructor.
     * @param $GpOrder
     * @param int $hours_to_wait
     * @param string $time_of_day
     */
    public function __construct($GpOrder, $hours_to_wait = 30, $time_of_day = '11:00')
    {
        parent::__construct($GpOrder);
        $this->hours_to_wait = $hours_to_wait;
        $this->time_of_day = $time_of_day;
    }

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
     * Cancel the any events that are not longer needed and push this event to the comm calendar
     */
    public function publish(): void
    {
        //  @TODO Uncomment this out when going live
        //  $this->order->cancelEvents(['Refill Reminder']);
        $this->publishEvent();
    }
}
