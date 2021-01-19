<?php

namespace  Sirum\AWS\SQS\GoogleAppRequest\Invoice;

use Sirum\AWS\SQS\GoogleAppRequest\HelperRequest;

/**
 * Base level class for all Google Doc requests
 */
class DeleteMultiples extends HelperRequest
{
    protected $properties = [
        'type',
        'method',
        'folderId',
        'fileName'
    ];

    protected $required = [
        'method',
        'folderId',
        'fileName'
    ];

    /**
     * Set the method to the default value for a delete request
     * @param string $requests  (Optional) The initial data
     */
    public function __construct($request = null)
    {
        $this->method = 'v2/removeFile';
        parent::__construct($request);
    }
}
