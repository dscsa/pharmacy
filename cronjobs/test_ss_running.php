<?php

ini_set('memory_limit', '512M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';
require_once 'helpers/helper_laravel.php';
require_once 'helpers/helper_pagerduty.php';
require_once 'keys.php';

use GoodPill\Storage\MSSQL;

class GrxMaster
{
  /**
   * PDO object to hold goodpill database.  Placed in this class so we
   * can use the MySQL originating class to load multiple databases
   * @var PDO
   */
    private static $carepoint;

    /**
     * Create a MySQL PDO object that is connected to the goodpill Database
     * @return PDO
     */
    public static function getConnection()
    {
        if (!(self::$carepoint instanceof PDO)) {
            self::$carepoint = MSSQL::getPDO(MSSQL_CP_IP, 'grx_master', MSSQL_CP_USER, MSSQL_CP_PWD);
        }

        return self::$carepoint;
    }
}

if (file_exists('/tmp/last_ss_id.json')) {
    $last_ss = json_decode(file_get_contents('/tmp/last_ss_id.json'));
} else {
    $last_ss = (object) [
        'ss_id' => null,
        'time' => null
    ];
}

$grx = GrxMaster::getConnection();

$pdo = $grx->prepare("SELECT TOP 1 * FROM dbo.ss_trans ORDER BY ss_trans_id DESC;");
$pdo->execute();
$trans = $pdo->fetch();

// If the Id's are the same, check the timestamp
if ($last_ss->ss_id == $trans['ss_trans_id']) {
    $offset = '18';
    if (date('N') >= 6) {
        $offset = '48';
    }
    if (strtotime("+{$offset} hour", $last_ss->time) < time()) {
        pd_guardian("There have not been any new surescript transactions in more than {$offset} hours", 'ss-not-running');
        $last_ss->time = time();
    }
} else {
    $last_ss->ss_id = $trans['ss_trans_id'];
    $last_ss->time = time();
}

file_put_contents('/tmp/last_ss_id.json', json_encode($last_ss));
