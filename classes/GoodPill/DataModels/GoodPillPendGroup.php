<?php

namespace GoodPill\DataModels;

use GoodPill\Storage\Goodpill;
use GoodPill\GPModel;


use \PDO;
use \Exception;

/**
 * A class for loading Order data.  Right now we only have a few fields defined
 */
class GoodPillPendGroup extends GPModel
{
    /**
     * List of possible properties names for this object
     * @var array
     */
    protected $fields = [
        'invoice_number',
        'pend_group',
        'initial_pend_date',
        'last_pend_date'
    ];

    /**
     * The table name to store the notifications
     * @var string
     */
    protected $table_name = "gp_pend_group";


    /**
     * Name of the primary key or an array oc fields to make key
     * @var string|array
     */
    protected $primary_key = 'invoice_number';
}
