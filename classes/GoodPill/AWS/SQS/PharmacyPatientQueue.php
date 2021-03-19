<?php

namespace  GoodPill\AWS\SQS;

/**
 * Class for accessing the patient queue
 */
class PharmacyPatientQueue extends Queue
{
    public function __construct()
    {
        parent::__construct(SQS_PHARMACY_PATIENT);
    }
}
