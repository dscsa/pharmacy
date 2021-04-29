<?php

namespace GoodPill\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class WpPostmetum
 *
 * @property int $meta_id
 * @property int $post_id
 * @property string|null $meta_key
 * @property string|null $meta_value
 *
 * @package App\Models
 */
class WpPostMeta extends Model
{
    protected $table = 'wp_postmeta';
    protected $primaryKey = 'meta_id';
    public $timestamps = false;

    protected $casts = [
        'post_id' => 'int'
    ];

    protected $fillable = [
        'post_id',
        'meta_key',
        'meta_value'
    ];
}
