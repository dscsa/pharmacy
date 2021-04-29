<?php

namespace GoodPill\Events;

use GoodPill\Events\CalendarEvent;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Events\SalesforceComm;

class CommCalEntry
{
    protected $email;
    protected $sms;
    protected $salesforce;

    public function setEmail(EmailComm $email)
    {
    }

    public function setSms(SmsComm $sms)
    {
    }

    public function setSalesforce(SalesforceComm $salesforce)
    {
    }

    public function getEventJson()
    {

        $comms = [];
        
        if (isset($this->email)) {
            $comms['email'] = $this->email->delivery();
        }

        if (isset($this->sms)) {
            $comms['sms'] = $this->sms->delivery();
        }

        if (isset($this->salesforce)) {
            $comms['salesforce'] = $this->salesforce->delivery();
        }


        return $comms;
    }
}
