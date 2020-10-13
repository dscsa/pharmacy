<?php

namespace  Sirum\Storage;

use PDO;

/**
 * Class for Database Access
 */
class MySQL
{

    /**
     * PDO object to hold the primary
     * @var PDO
     */
    private static $mysql;

    /**
     * function to grab the connection
     * @method getConnection
     *
     * @param  string $host The hostname of the database server
     * @param  string $db   The name of the Database
     * @param  string $user The hostname of the database server
     * @param  string $pass   The name of the Database
     *
     * @return PDO                      Database Object
     *
     * @todo Implement lag check to turn over primary on excessive lag
     */
    public static function getConnection($host, $db, $user, $pass)
    {

        if (!(self::$mysql instanceof PDO)) {
            self::$mysql = self::getPDO(DB_HOST, DB_NAME);
        }

        return self::$mysql;
    }

    /**
     * Get the PDO object for the Database
     * @method getPDO
     * @param  string $host The hostname of the database server
     * @param  string $db   The name of the Database
     * @param  string $user The hostname of the database server
     * @param  string $pass   The name of the Database
     * @return \PDO                A PDO object for accessing the DB
     */
    protected static function getPDO($host, $db, $user, $pass)
    {
        try {
            $objPdo = new PDO(
                "mysql:host=" . $host
                 . ";dbname=" . $db
                 . ";port=" . DB_PORT
                 . ";charset=utf8",
                $user,
                $pass
            );

            $objPdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
            $objPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $objPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
        return $objPdo;
    }
}
