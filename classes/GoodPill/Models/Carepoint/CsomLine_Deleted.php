<?php
namespace GoodPill\Models\Carepoint;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CsomLine
 *
 * Stores the association between an order and an rx
 *
 */
class CsomLine_Deleted extends CsomLine
{
    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'csomline_Deleted';

    use GoodPill\Traits\IsNotDeleteable;

}
