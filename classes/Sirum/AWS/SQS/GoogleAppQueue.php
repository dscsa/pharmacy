<?php

namespace  Sirum\AWS\SQS;

/**
 * Class for access Amazon Simple Queuing Service
 */
class GoogleAppQueue extends Queue
{
    public function __construct()
    {
        parent::__construct('gdoc_requests.fifo');
    }
}
