<?php

namespace  Sirum\AWS\SQS\GoogleDocsRequests;

/**
 * Base level class for all Google Doc requests
 */
class Delete extends HelperRequest
{
    protected $properties = [
        'type',
        'method',
        'folderId',
        'fileId',
        'fileName'
    ];

    protected $required = [
        'method'
    ];
}
