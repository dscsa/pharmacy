<?php

namespace GoodPill\Events;

/**
 * The basic abstract for a communication element.  It includes the array Model functionality
 * and defines the delivery as an incomplete delivery() function that is used for actually 
 * generating the output array
 */
abstract class Comm
{
    use \GoodPill\Traits\UsesArrayModelStorage;

    abstract public function delivery() : array;
}
