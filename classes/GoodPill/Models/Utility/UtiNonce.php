<?php

namespace GoodPill\Models\Utility;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UtiNonce
 */
class UtiNonce extends Model
{
    /**
     * The table
     * @var string
     */
    protected $table = 'uti_nonce';

    /**
     * The primary key field
     * @var string
     */
    protected $primaryKey = 'nonce_id';

    /**
     * Does the primary key auto increment
     * @var boolean
     */
    public $incrementing = true;

    /**
     * Does the table use timestamps
     * @var boolean
     */
    public $timestamps = false;

    /**
     * Which properties are cast beofre setting
     * @var array
     */
    protected $casts = [
        'nonce_id' => 'int',
    ];

    /**
     * Which properties are dates
     * @var array
     */
    protected $dates = [
        'expires',
    ];

    /**
     * Which properties can be filled
     * @var array
     */
    protected $fillable = [
        'nonce_id',
        'token',
        'expires',
        'user'
    ];

    /**
     * create a 45 day nonce
     * @param integer $lifetime Number of days the nonce is valid.
     * @return void
     */
    public function generate(int $lifetime = 45)
    {
        $this->token = sha1(\uniqid());
        $this->expires = date('c', strtotime("+{$lifetime} day"));
    }
}
