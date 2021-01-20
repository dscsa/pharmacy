<?php

namespace  Sirum\AWS\SQS\GoogleAppRequest\Invoice;

use Sirum\AWS\SQS\GoogleAppRequest\HelperRequest;

/**
 * Base level class for all Google Doc requests
 */
class Update extends MergeRequest
{
    protected $properties = [
        'type',
        'method',
        'fileId'
    ];

    protected $required = [
        'method',
        'fileId',
        'order'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        $this->method = 'v2/updateFile';
        parent::__construct($request);
    }
}
