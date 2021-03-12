<?php

namespace  GoodPill\AWS\SQS;

/**
 * Class for accessing the asynchronous queue
 */
class PharmacyAsyncQueue extends Queue
{
    public function __construct()
    {
        parent::__construct(SQS_PHARMACY_ASYNC);
    }
}
