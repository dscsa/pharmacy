<?php

use GoodPill\Models\GpOrder;

interface DaysMessageInterface {
    public function isNoTransfer() : bool;
    public function isAddedManually() : bool;
    public function isWebform() : bool;
    public function isNotOffered() : bool;
    public function isRefill(GpOrder $order) : bool;
    public function isRefillOnly() : bool;
    public function isNotRxParsed() : bool;
    //public function isDuplicateGsn() : bool;

    public function getDaysLeftBeforeExpiration();
    public function getDaysLeftInRefills();
    public function getDaysLeftInStock();
    public function getDaysDefault();

}