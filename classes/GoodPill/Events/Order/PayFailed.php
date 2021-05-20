<?php

namespace GoodPill\Events\Order;

use GoodPill\Events\SalesforceComm;
use GoodPill\Events\SmsComm;

/**
 * Class PayFailed
 * @package GoodPill\Events\Order
 */
class PayFailed extends OrderEvent
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
    protected $template_path = 'Order/Pay_failed';

    public function getOrderStage()
    {
        $stage = $this->order->order_stage_wc;

        $this->order_data['is_card_missing'] = $stage === 'wc-late-card-missing' || false;
        $this->order_data['is_card_failed'] = $stage === 'wc-late-card-failed' || false;
        $this->order_data['is_card_expired'] =  $stage === 'wc-late-card-expired' || false;
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
