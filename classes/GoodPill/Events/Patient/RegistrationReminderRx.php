<?php

namespace GoodPill\Events\Patient;

use GoodPill\Events\Event;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;
use GoodPill\Models\GpPatient;
use GoodPill\Models\GpRxsGrouped;

/**
 * A Class to define the event a Registration Reminder for an Rx is sent out
 */
class RegistrationReminderRx extends Event
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
     * Holds the RxsGrouped item that we need to notify the patient of
     * @TODO - convert item to grouped and eventually single.
     * @TODO - explore why $item in rxs_created2 loop doesn't have an rx_number
     * @TODO - $item has provider and drug info
     * @var GpRxsGrouped
     */
    protected $rxs_grouped;

    protected $item;

    /**
     * The type of event.  This is used for the event title
     * @var string
     */
    public $type;

    /**
     * Should be defined by Child class
     * @var string
     */
    protected $template_path = 'Patient/RegistrationReminderRx';

    /**
     * Make it so
     * @param GpPatient|null $patient Optional. Will preset the patient if passed.
     */
    public function __construct(?GpPatient $patient = null, $item = null)
    {
        $this->type = 'Patient Registration';

        if (!is_null($patient)) {
            $this->setPatient($patient);
        }

        //$rx = GpRxsGrouped::where('rx_numbers', '=', $rx_numbers)->first();
        if (!is_null($item))
        {
            $this->setItem($item);
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

    public function setRx(GpRxsGrouped $groupedRx) : void
    {
        $this->rxs_grouped = $groupedRx;
    }

    public function setItem(array $item) : void
    {
        $this->item = $item;
    }


    /**
     * The the data about a patient so the template can be rendered
     * @return string
     */
    public function getPatientData() : array
    {
        $patient = $this->patient;
        $data    = $patient->toArray();

        //  @TODO - How can we move from passing $item to using a model
        //  Need to get provider information and drug name
        if (! is_null($this->item)) {
            $data['provider'] =  [
                'provider_first_name' => $this->item['provider_first_name'],
                'provider_last_name' => $this->item['provider_last_name'],
                'provider_clinic' => $this->item['provider_clinic'],
                'provider_phone' => $this->item['provider_phone'],
            ];

            $data['order_item'] = $this->item['drug_name'];
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
        //  Should not need to ever call the `Needs Form` event again since all usage is replaced
        //  With this event
        $this->patient->cancelEvents([
            'Needs Form',
            'Patient Registration'
        ]);

        $this->patient_label = $this->patient->getPatientLabel();
        $this->publishEvent();
    }
}
