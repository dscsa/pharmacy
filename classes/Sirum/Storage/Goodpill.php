<?php

namespace  Sirum\Storage;

use PDO;

/**
 * Class for Database Access
 */
class Goodpill extends MySQL
{

  /**
   * PDO object to hold goodpill database.  Placed in this class so we
   * can use the MySQL originating class to load multiple databases
   * @var PDO
   */
  private static $mysql;

  /**
   * Create a MySQL PDO object that is connected to the goodpill Database
   * @return [type] [description]
   */
  public static function getConnection() {
    return parent::getConnection(MYSQL_WC_IP, 'goodpill', MYSQL_WC_USER, MYSQL_WC_PWD);
  }
}
