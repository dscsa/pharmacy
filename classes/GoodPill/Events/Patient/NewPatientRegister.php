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
class NewPatientRegister extends Event
{
    /**
     * Hold the patient for this event
     * @var GoodPill\Models\GpPatient
     */
    protected $patient;

    /**
     * Hold the patient data that is used to render the messages
     * @var array
     */
    protected $patient_data;

    /**
     * More data that will be added to the base patient data
     * @var array
     */
    protected $additional_data = [];

    /**
     * The $groups data that is needed to determine when to send the message and drugs to list
     * @var null
     */
    protected $groups = null;

    /**
     * The type of event.  This is used for the event title
     * @var string
     */
    public $type;

    /**
     * Should be defined by Child class
     * @var string
     */
    protected $template_path = 'Patient/NewPatientRegister';

    /**
     * Make it so
     * @param GpPatient|null $patient Optional. Will preset the patient if passed.
     */
    public function __construct(?GpPatient $patient = null, $groups = null)
    {
        $this->type = 'Patient Registration';
        $this->groups = $groups;

        if (!is_null($patient)) {
            $this->setPatient($patient);
        }

        $hour_added = substr($groups['ALL'][0]['order_date_added'], 11, 2);

        if ($hour_added < 10) {
            //A if before 10am, the first one is at 10am, the next one is 5pm, then 10am tomorrow, then 5pm tomorrow
            $this->hours_to_wait = [0, 0, 24, 24, 24*7, 24*14];
            $this->hour_of_day   = ['11:00', '17:00', '11:00', '17:00', '17:00', '17:00'];
        } elseif ($hour_added < 17) {
            //A if before 5pm, the first one is 10mins from now, the next one is 5pm, then 10am tomorrow, then 5pm tomorrow
            $this->hours_to_wait = [10/60, 0, 24, 24, 24*7, 24*14];
            $this->hour_of_day   = [0, '17:00', '11:00', '17:00', '17:00', '17:00'];
        } else {
            //B if after 5pm, the first one is 10am tomorrow, 5pm tomorrow, 10am the day after tomorrow, 5pm day after tomorrow.
            $this->hours_to_wait = [24, 24, 48, 48, 24*7, 24*14];
            $this->hour_of_day   = ['11:00', '17:00', '11:00', '17:00', '17:00', '17:00'];
        }
    }

    /**
     * Set the patient for this event
     * @param GpPatient $patient The patient.
     * @return void
     */
    public function setPatient(GpPatient $patient) : void
    {
        $this->patient = $patient;
    }

    /**
     * An array of data to add to the data available to the templates
     * @param array $data Anything you would like to add.
     * @return void
     */
    public function setAdditionalData(array $data) : void
    {
        $this->additional_data = $data;
    }

    /**
     * The the data about a patient so the template can be rendered
     * @return string
     */
    public function getPatientData() : array
    {
        $patient = $this->patient;
        $data    = $patient->toArray();

        if (!empty($this->additional_data)) {
            $data['additional'] = $this->additional_data;
        }

        if (! is_null($this->groups)) {
            $data['groups'] = $this->groups;
            $data['provider'] = [
                'provider_first_name' => $this->groups['ALL'][0]['provider_first_name'],
                'provider_last_name' => $this->groups['ALL'][0]['provider_last_name'],
                'provider_clinic' => $this->groups['ALL'][0]['provider_clinic'],
                'provider_phone' => $this->groups['ALL'][0]['provider_phone']
            ];
            $data['order_items'] = $this->groups['NOFILL_ACTION'];
        }

        return $data;
    }

    /**
     * Get the email portion of a communication
     * @return EmailComm
     */
    public function getEmail() : ?EmailComm
    {
        if (empty($this->patient->email)) {
            echo "There Is No Patient Email \n";
            return null;
        }
        $email              = new EmailComm();
        $email->subject     = $this->render('email_subject');
        $email->message     = $this->render('email');
        $email->email       = $this->patient->email;

        return $email;
    }

    /**
     * Generate the SMS part of the communication Event
     * @return SmsComm
     */
    public function getSms() : ?SmsComm
    {
        return null;
    }

    /**
     * Generate the Salesforce portion of the Communication event
     * @return SalesforceComm
     */
    public function getSalesforce() : ?SalesforceComm
    {
        if (! $this->patient instanceof GpPatient) {
            return null;
        }

        $patient               = $this->patient;
        $salesforce            = new SalesforceComm();
        $salesforce->contact   = $this->patient->getPatientLabel();
        $salesforce->subject   = "Call to Register Patient";
        $salesforce->body      = $this->render('salesforce');
        $salesforce->assign_to = '.Missing Contact Info';
        return $salesforce;
    }

    /**
     * Get the title for the event.
     * @return string
     */
    public function getTitle() : string
    {
        return sprintf(
            '%s: %s. Created: %s',
            $this->type,
            $this->patient->getPatientLabel(),
            date('Y-m-d H:i:s')
        );
    }

    /**
     * Render a template using the patient data
     * @param  string $template The template name to render.
     * @return string
     */
    protected function render(string $template) : string
    {
        $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

        $template_path = TEMPLATE_DIR . "{$this->template_path}/{$template}.mustache";

        return $m->render(
            file_get_contents($template_path),
            $this->getPatientData()
        );
    }

    /**
     * Publish the events
     * Cancel the any events that are not longer needed and push this event to the com calendar
     */
    public function publish() : void
    {
        // Can't send notfications if the order doesn't exist
        if (!$this->patient) {
            return;
        }
        $this->patient->cancelEvents([
            'Needs Form',
            'Patient Registration'
        ]);
        $this->patient_label = $this->patient->getPatientLabel();
        $this->publishEvent();
    }
}
