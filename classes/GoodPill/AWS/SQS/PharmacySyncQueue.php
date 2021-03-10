<?php

namespace  GoodPill\AWS\SQS;

/**
 * Class for access Amazon Simple Queuing Service
 */
class PharmacySyncQueue extends Queue
{
    public function __construct()
    {
        parent::__construct(SQS_PHARMACY_SYNC);
    }
}
