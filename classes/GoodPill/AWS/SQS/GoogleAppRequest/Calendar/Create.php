<?php

namespace  GoodPill\AWS\SQS\GoogleAppRequest\Calendar;

use GoodPill\AWS\SQS\GoogleAppRequest\HelperRequest;

/**
 * Base level class for all Google Doc requests
 */
class Create extends HelperRequest
{
    protected $properties = [
        'cal_id',
        'method',
        'start',
        'title',
        'description',
        'hours',
        'type'
    ];

    protected $required = [
        'cal_id',
        'method',
        'start',
        'title',
        'description',
        'hours',
        'type'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        $this->method = 'v2/createEvent';
        parent::__construct($request);
    }
}
