<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WpPost
 * 
 * @property int $ID
 * @property int $post_author
 * @property Carbon $post_date
 * @property Carbon $post_date_gmt
 * @property string $post_content
 * @property string $post_title
 * @property string $post_excerpt
 * @property string $post_status
 * @property string $comment_status
 * @property string $ping_status
 * @property string $post_password
 * @property string $post_name
 * @property string $to_ping
 * @property string $pinged
 * @property Carbon $post_modified
 * @property Carbon $post_modified_gmt
 * @property string $post_content_filtered
 * @property int $post_parent
 * @property string $guid
 * @property int $menu_order
 * @property string $post_type
 * @property string $post_mime_type
 * @property int $comment_count
 *
 * @package App\Models
 */
class WpPost extends Model
{
	protected $table = 'wp_posts';
	protected $primaryKey = 'ID';
	public $timestamps = false;

	protected $casts = [
		'post_author' => 'int',
		'post_parent' => 'int',
		'menu_order' => 'int',
		'comment_count' => 'int'
	];

	protected $dates = [
		'post_date',
		'post_date_gmt',
		'post_modified',
		'post_modified_gmt'
	];

	protected $hidden = [
		'post_password'
	];

	protected $fillable = [
		'post_author',
		'post_date',
		'post_date_gmt',
		'post_content',
		'post_title',
		'post_excerpt',
		'post_status',
		'comment_status',
		'ping_status',
		'post_password',
		'post_name',
		'to_ping',
		'pinged',
		'post_modified',
		'post_modified_gmt',
		'post_content_filtered',
		'post_parent',
		'guid',
		'menu_order',
		'post_type',
		'post_mime_type',
		'comment_count'
	];
}
