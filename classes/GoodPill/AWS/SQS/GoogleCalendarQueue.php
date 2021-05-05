<?php

namespace  GoodPill\AWS\SQS;

/**
 * Class for access Amazon Simple Queuing Service
 */
class GoogleCalendarQueue extends Queue
{
    public function __construct()
    {
        parent::__construct(SQS_GDOC_CAL);
    }
}
