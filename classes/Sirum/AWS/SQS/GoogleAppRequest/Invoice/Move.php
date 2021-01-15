<?php

namespace  Sirum\AWS\SQS\GoogleAppRequest\Invoice;

use Sirum\AWS\SQS\GoogleAppRequest\HelperRequest;

/**
 * Base level class for all Google Doc requests
 */
class Move extends HelperRequest
{
    protected $properties = [
        'type',
        'method',
        'fileId'
    ];

    protected $required = [
        'method',
        'fileId'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        $this->method = 'v2/moveFile';
        parent::__construct($request);
    }
}
