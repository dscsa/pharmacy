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
    public function __construct($GpOrder, $groups, $hours_to_wait = 30, $time_of_day = '11:00')
    {
        parent::__construct($GpOrder);
        $this->groups = $groups;
        $this->hours_to_wait = $hours_to_wait;
        $this->time_of_day = $time_of_day;
    }

    /**
     * Publish the events
     * Cancel the any events that are not longer needed and push this event to the com calendar
     */
    public function publish(): void
    {
        print_r($this->time_of_day);
        print_r('we should just execute and finish');

        $this->publishEvent();
        /*
        // Can't send notifications if the order doesn't exist
        if (!$this->order) {
            return;
        }
        //  Order cancel events
        //  Refill Reminder

        $this->order->cancelEvents([
            'Refill Reminder'
        ]);

        //$patient->createEvent($this);
        */
    }
}
