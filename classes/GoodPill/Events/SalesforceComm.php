<?php

namespace GoodPill\Events;

use GoodPill\Events\Comm;

/**
 * This class is used to gereate a Comms compatible salesforce message
 */
class SalesforceComm extends Comm
{
    protected $properties = [
        'subject',
        'body',
        'contact',
        'assign_to',
        'due_date'
    ];

    protected $required = [
        'subject',
        'body',
    ];

    /**
     * Create a Comm Calendar compatible Delivery message
     * @return array
     */
    public function delivery() : array
    {
        return $this->toArray();
    }
}
