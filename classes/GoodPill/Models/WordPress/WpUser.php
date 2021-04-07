<?php

/**
 * Created by Reliese Model.
 */

namespace GoodPill\Models\WordPress;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use GoodPill\Models\WordPress\WpUserMeta;
/**
 * Class WpUser
 *
 * @property int $ID
 * @property string $user_login
 * @property string $user_pass
 * @property string $user_nicename
 * @property string $user_email
 * @property string $user_url
 * @property Carbon $user_registered
 * @property string $user_activation_key
 * @property int $user_status
 * @property string $display_name
 *
 * @package App\Models
 */
class WpUser extends Model
{
	protected $table = 'wp_users';
	protected $primaryKey = 'ID';
	public $timestamps = false;

	protected $casts = [
		'user_status' => 'int'
	];

	protected $dates = [
		'user_registered'
	];

	protected $fillable = [
		'user_login',
		'user_pass',
		'user_nicename',
		'user_email',
		'user_url',
		'user_registered',
		'user_activation_key',
		'user_status',
		'display_name'
	];

    public function meta() {
        return $this->hasMany(WpUserMeta::Class, 'user_id', 'ID');
    }
}
