<?php

namespace  Sirum\Storage;

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
  private static $goodpill;

  /**
   * Create a MySQL PDO object that is connected to the goodpill Database
   * @return PDO
   */
  public static function getConnection() {

    if (!(self::$goodpill instanceof PDO)) {
        self::$goodpill = Mysql::getPDO(MYSQL_WC_IP, 'goodpill', MYSQL_WC_USER, MYSQL_WC_PWD);
    }

    return self::$goodpill;
  }
}
