<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\Event;
use GoodPill\Events\SalesforceComm;
use GoodPill\Events\EmailComm;
use GoodPill\Events\SmsComm;
use GoodPill\Models\GpOrder;

class Completed extends Event
{
    /**
     * Hold the order for this event
     * @var GpOrder
     */
    protected $order;

    /**
     * Hold the order data that is used to render the messages
     * @var array
     */
    protected $order_data;

    public $type = 'Order Completed';

    public $subject = null;
    public $body = null;

    /**
     * Completed constructor.
     * @param GpOrder $order
     * @param String $subject
     * @param String $body - the name of the body template to call
     */
    public function __construct(GpOrder $order, String $body)
    {
        if (!is_null($order)) {
            $this->setOrder($order);
        }

        $this->body = $body;
    }

    /**
     * Set the order for this event
     * @param GpOrder $order The order
     */
    public function setOrder(GpOrder $order)
    {
        $this->order = $order;
    }

    /**
     * Create the json object needed for rendering the templates
     * Sample:
     *    {
     *         "first_name": "Ben",
     *         "last_name": "Brown",
     *         "invoice_number": 12345,
     *         "tracking_number": "1234123124123",
     *         "tracking_link": "http://www.gmail.com",
     *         "invoice_link": "http://www.gmail.com",
     *    }
     * @return array
     */
    public function getOrderData()
    {
        if (!isset($this->order_data)) {
            $order = $this->order;

            $data = [
                'first_name'         => $order->patient->first_name,
                'last_name'          => $order->patient->last_name,
                'birth_date'         => $order->patient->birth_date,
                'invoice_number'     => $order->invoice_number,
                'tracking_number'    => $order->tracking_number,
                'tracking_url'       => $order->getTrackingUrl(),
                'short_tracking_url' => $order->getTrackingUrl(true),
                'invoice_url'        => $order->getInvoiceUrl(),
                'short_invoice_url'  => $order->getInvoiceUrl(true)
            ];


            $this->order_data = $data;
        }

        return $this->order_data;
    }

    /**
     * Publish the events
     * Cancel the any events that are not longer needed and push this event to the com calendar
     */
    public function publish() : void
    {

        // Can't send notfications if the order doesn't exist
        if (!$this->order) {
            return;
        }

        $patient = $this->order->patient;
        $this->patient_label = $patient->getPatientLabel();
        $this->publishEvent();
    }

    /**
     * Get the email portion of a communication
     * @return EmailComm
     */
    public function getEmail() : ?EmailComm
    {
        // if we don't have an email address we can't send an email
        if (empty($this->order->patient->email)) {
            return null;
        }

        $email              = new EmailComm();
        $email->subject     = $this->render('email_subject');
        $email->message     = $this->render($this->body);
        $email->attachments = [$this->order->invoice_doc_id];
        $email->email       = 'jesse@sirum.org';
        //$email->email   = $this->order->patient->email;
        return $email;
    }

    /**
     * Generate the SMS part of the communication Event
     * @return SmsComm
     */
    public function getSms() : ?SmsComm
    {
        // if we don't have an sms numbers
        if (empty($this->order->patient->getPhonesAsString())) {
            return null;
        }

        $sms          = new SmsComm();
        // $sms->sms     = $patient->getPhonesAsString();
        $sms->sms     = '';
        $sms->message = $this->render('sms');
        return $sms;
    }

    /**
     * Generate the Salesforce portion of the Communication event
     * @return SalesforceComm
     */
    public function getSalesforce() : ?SalesforceComm
    {
        $patient             = $this->order->patient;
        $salesforce          = new SalesforceComm();
        $salesforce->contact = $patient->getPatientLabel();
        $salesforce->subject = "Auto Email/Text " . $this->render('email_subject');
        $salesforce->body    = $this->render('sms');
        return $salesforce;
    }

    /**
     * Render a template using the order data
     * @param  string $template The template name to render
     * @return string
     */
    protected function render($template)
    {
        $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

        return $m->render(
            file_get_contents("/goodpill/webform/templates/Order/Completed/". $template . '.mustache'),
            $this->getOrderData()
        );
    }
}
