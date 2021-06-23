<?php

namespace  GoodPill\AWS\SQS\GoogleAppRequest\Calendar;

use GoodPill\AWS\SQS\GoogleAppRequest\HelperRequest;

/**
 * Base level class for all Google Doc requests
 */
class SearchAndDelete extends HelperRequest
{
    protected $properties = [
        'cal_id',
        'word_search',
        'hours',
        'regex_search',
        'method',
        'type'
    ];

    protected $required = [
        'cal_id',
        'word_search',
        'method'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        $this->method = 'v2/searchAndDeleteEvents';
        parent::__construct($request);
    }
}
