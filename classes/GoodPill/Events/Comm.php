<?php

namespace GoodPill\Events;

abstract class Comm
{
    use \GoodPill\Traits\ArrayModelStorage;

    abstract public function delivery() : array;
}
