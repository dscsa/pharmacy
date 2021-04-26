<?php

/**
 * Created by Reliese Model.
 */

namespace GoodPill\Models\WordPress;

use Illuminate\Database\Eloquent\Model;

require_once "dbs/mssql_cp.php";
require_once "dbs/mysql_wc.php";
/**
 * Class WpUsermetum
 *
 * @property int $umeta_id
 * @property int $user_id
 * @property string|null $meta_key
 * @property string|null $meta_value
 *
 * @package App\Models
 */
class WpUserMeta extends Model
{
	protected $table = 'wp_usermeta';
	protected $primaryKey = 'umeta_id';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int'
	];

	protected $fillable = [
		'user_id',
		'meta_key',
		'meta_value'
	];
}
