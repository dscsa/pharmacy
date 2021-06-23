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
class CarepointLabelChanged extends Event
{
    /**
     * Hold the patien for this event
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
     * The type of event.  This is used for the event title
     * @var string
     */
    public $type;

    /**
     * Should be defined by Child class
     * @var string
     */
    protected $template_path = 'Patient/CarepointLabelChanged';

    /**
     * Make it so
     * @param GpPatient|null $patient Optional. Will preset the patient if passed.
     */
    public function __construct(?GpPatient $patient = null)
    {
        $this->type = 'Label Change';

        if (!is_null($patient)) {
            $this->setPatient($patient);
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

        return $data;
    }

    /**
     * Get the email portion of a communication
     * @return EmailComm
     */
    public function getEmail() : ?EmailComm
    {
        return null;
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
        $salesforce->subject   = "Auto Email/Text " . $this->render('subject');
        $salesforce->body      = $this->render('salesforce');
        $salesforce->assign_to = '.Testing';
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

        $patient = $this->patient;

        $patient->createEvent($this);
    }
}
