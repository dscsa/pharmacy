<?php

namespace  GoodPill\Storage;

use \PDO;

/**
 * Class for Database Access
 */
class Goodpill
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
            self::$carepoint = MSSQL::getPDO(MSSQL_CP_IP, 'cph', MSSQL_CP_USER, MSSQL_CP_PWD);
        }

        return self::$carepoint;
    }
}
