<?php

namespace GoodPill\Events\Patient;

use GoodPill\Events\Event;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpPatient;

/**
 * A Class to define the event when a CarepointPatientLabel has changed
 */
class RegistrationReminder extends NewPatientRegister
{

    /**
     * Should be defined by Child class
     * @var string
     */
    protected $template_path = 'Patient/RegistrationReminder';
}
