<?php

namespace  GoodPill\AWS\SQS\GoogleAppRequest\Invoice;

use GoodPill\AWS\SQS\GoogleAppRequest\MergeRequest;

/**
 * Base level class for all Google Doc requests
 */
class Complete extends MergeRequest
{
    protected $properties = [
        'type',
        'method',
        'fileId',
        'orderDetails'
    ];

    protected $required = [
        'method',
        'fileId',
        'orderDetails'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        $this->method = 'v2/completeInvoice';
        parent::__construct($request);
    }
}
