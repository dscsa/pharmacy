<?php

namespace GoodPill\Models\WooCommerce;


class WcPatient
{
    public $id;

    public function __construct(int $id) {
        $this->id;
    }

    public function updateMeta($key, $value) {

    }

    public function updateMetaBatch($meta) {
        foreach ($meta as $key => $value) {
            $this->updateMeta($key, $value);
        }
    }
}
