<?php

namespace GoodPill\Events;

abstract class Comm
{
    use \GoodPill\Traits\UsesArrayModelStorage;

    abstract public function delivery() : array;
}
