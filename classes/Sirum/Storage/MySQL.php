<?php

namespace  Sirum\Storage;

use PDO;

/**
 * Class for Database Access
 */
class MySQL
{
    /**
     * Get the PDO object for the Database
     * @method getPDO
     * @param  string $host The hostname of the database server
     * @param  string $db   The name of the Database
     * @param  string $user The hostname of the database server
     * @param  string $pass   The name of the Database
     * @return \PDO                A PDO object for accessing the DB
     */
    public static function getPDO($host, $db, $user, $pass)
    {
        try {
            $objPdo = new PDOWrap(
                "mysql:host={$host};dbname={$db};port=3306;charset=utf8",
                $user,
                $pass
            );
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

        return $objPdo;
    }
}
