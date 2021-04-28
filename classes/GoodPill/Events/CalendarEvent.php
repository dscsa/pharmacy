<?php

namespace GoodPill\Events;

use GoodPill\Events\CalendarEvent;

class CalendarEvent
{
    protected $email;
    protected $sms;
    protected $salesforce;

    public function setEmail(EmailEvent $email)
    {
    }

    public function setSms(SmsEvent $sms)
    {
    }

    public function setSalesforce(SalesforceEvent $salesforce)
    {
    }

    public function renderEmail()
    {
        return null;
    }

    public function renderSms()
    {
        return null;
    }

    public function renderSalesforce()
    {
        return null;
    }

    public function getEventJson()
    {
        $email = $this->renderEmail();
        if (!is_null($email)) {
            $this->email = $email;
        }

        $sms = $this->renderSms();
        if (!is_null($sms)) {
            $this->sms = $sms;
        }

        $salesforce = $this->renderSalesforce();
        if (!is_null($sms)) {
            $this->salesforce = $salesforce;
        }

        return array_filter(
            [
                'email'      => $this->email,
                'sms'        => $this->sms,
                'salesforce' => $this->salesforce
            ]
        );
    }
}
