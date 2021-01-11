<?php

namespace Sirum\DataModels;

use Sirum\Storage\Goodpill;
use Sirum\GPModel;

use \PDO;
use \Exception;

/**
 * A class for loading Order data.  Right now we only have a few fields defined
 */
class Order extends GPModel
{
    /**
     * List of possible properties names for this object
     * @var array
     */
    protected $fields = [
        "invoice_number",
        "invoice_doc_id"
    ];

    /**
     * The table name to store the notifications
     * @var string
     */
    protected $table_name = "gp_orders";
}
