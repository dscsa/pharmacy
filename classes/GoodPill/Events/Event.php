<?php

namespace GoodPill\Events;

use GoodPill\Events\ComCalEntry;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;

abstract class Event
{
    /**
     * Create a comm calendar entry then post it to the calendar
     * @return void
     */
    public function publishEvent()
    {
        // If we have an email address create the email
        // If we have an SMS, create the SMS
        // Create the Salesforce event from the SMS or the Email
        // Remove any other shipping events from the calendar
        // Create the new event
    }

    abstract public function getSms() : SmsComm;
    abstract public function getEmail() : EmailComm;
    abstract public function getSalesforce() : SalesforceComm;
}
