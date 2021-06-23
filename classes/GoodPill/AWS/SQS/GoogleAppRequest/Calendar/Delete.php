<?php

namespace  GoodPill\AWS\SQS\GoogleAppRequest\Calendar;

use GoodPill\AWS\SQS\GoogleAppRequest\HelperRequest;

/**
 * Base level class for all Google Doc requests
 */
class Delete extends HelperRequest
{
    protected $properties = [
        'cal_id',
        'method',
        'ids',
        'type'
    ];

    protected $required = [
        'cal_id',
        'method',
        'ids',
        'type'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        $this->method = 'v2/removeEvents';
        parent::__construct($request);
    }
}
