<?php

namespace  Sirum\AWS\SQS\GoogleDocsRequests;

/**
 * Base level class for all Google Doc requests
 */
class Delete extends BaseRequest
{
    protected $properties = [
        'type',
        'method',
        'folder',
        'fileId',
        'fileName'
    ];

    protected $required = [
        'method',
        'folder'
    ];
}
