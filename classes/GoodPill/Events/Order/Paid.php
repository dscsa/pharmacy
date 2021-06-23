<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\SalesforceComm;
use GoodPill\Events\SmsComm;

class Paid extends OrderEvent
{
    /**
     * The name of the event type
     * @var string
     */
    public $type = 'Order Paid';

    /**
     * The path to the templates
     * @var string
     */
    protected $template_path = 'Order/Paid';

    public function getOrderStage()
    {
        $stage = $this->order->order_stage_wc;

        $this->order_data['is_card_pay'] = $stage === 'wc-done-card-pay' || false;
        $this->order_data['is_mail_pay'] = $stage === 'wc-done-mail-pay' || false;
        $this->order_data['is_auto_pay'] =  $stage === 'wc-done-auto-pay' || false;
    }

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
        return null;
    }

    /**
     * Publish the events
     * Cancel the any events that are not longer needed and push this event to the com calendar
     */
    public function publish() : void
    {
        $this->getOrderStage();
        // Can't send notifications if the order doesn't exist
        if (!$this->order) {
            return;
        }

        $stage = $this->order->order_stage_wc;
        if ($stage) {
            $patient = $this->order->patient;
            $this->patient_label = $patient->getPatientLabel();
            $this->publishEvent();
        }


    }
}
