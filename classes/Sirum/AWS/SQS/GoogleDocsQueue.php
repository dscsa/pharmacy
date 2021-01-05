<?php

namespace  Sirum\Aws\SQS;

/**
 * Class for access Amazon Simple Queuing Service
 */
class GoogleDocsQueue extends Queue
{
    public function __construct()
    {
        parent::__construct('gdoc_requests.fifo');
    }
}
