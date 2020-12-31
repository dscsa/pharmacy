<?php

namespace  Sirum\Aws\SQS;

/**
 * Class for access Amazon Simple Queuing Service
 */
class GoogleDocdsQueue extends Queue
{
    public function __construct() {
        parent::_construct('gdoc_requests.fifo');
    }
}
