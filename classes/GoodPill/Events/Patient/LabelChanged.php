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
abstract class CarepointLabelChanged extends Event
{
    /**
     * Hold the order for this event
     * @var GpOrder
     */
    protected $patient;

    /**
     * Hold the order data that is used to render the messages
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
     * @param GpPatient|null $patient Optional. Will preset the order if passed.
     */
    public function __construct(?GpPatient $patient = null)
    {
        $this->type = 'Label Change';

        if (!is_null($patient)) {
            $this->setPatient($patient);
        }
    }

    /**
     * Set the order for this event
     * @param GpPatient $patient The patient.
     * @return void
     */
    public function setPatient(GpPatient $patient) : void
    {
        $this->order = $patient;
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
        $data    = $patient->getArributes();

        if (!empty($this->additional_data)) {
            $data['additional'] = $this->additional;
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
        $patient             = $this->patient;
        $salesforce          = new SalesforceComm();
        $salesforce->contact = $patient->getPatientLabel();

//@todo update events
        $salesforce->subject = "Auto Email/Text " . $this->render('subject');
        $salesforce->body    = $this->render('salesforce');
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
     * Render a template using the order data
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
}
