<?php

namespace  Sirum\AWS\SQS\GoogleAppRequest\Invoice;

/**
 * Base level class for all Google Doc requests
 */
class Update extends HelperRequest
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
    public function __construct($request = null) {
        $this->method = 'v2/updateFile';

        parent::__construct($request);
    }
}
