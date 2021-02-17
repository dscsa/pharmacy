<?php

namespace  Sirum\Storage;

use\PDO;

/**
 * This class wraps the PDO object so we can catch a gone away error
 * and try the query again
 */
class PDOWrap
{
    /**
     * The PDO Object
     * @var \PDO
     */
    protected $pdo;

    /**
     * The Connection String
     * @var string
     */
    protected $conn_string;

    /**
     * The User Name
     * @var string
     */
    protected $user;

    /**
     * The password
     * @var string
     */
    protected $pass;

    /**
     * Errors we should check for and reconnect on
     * @var array
     */
    protected $reconnect_on = [
        1317, // interrupted
        2002, // refused
        2006  // gone away
    ];

    /**
     * Build the pdo object so we can query the DB
     * @param string $conn_string The connection string
     * @param string $user        The username
     * @param string $pass        The password
     */
    public function __construct(string $conn_string, string $user, string $pass)
    {
        $this->objPdo = new PDO(
            $conn_string,
            $user,
            $pass
        );

        $this->conn_string = $conn_string;
        $this->user = $user;
        $this->pass = $pass;

        $this->connect();
    }

    /**
     * Create the actual PDO object
     * @return void
     */
    private function connect()
    {
        unset($this->pdo);

        $this->pdo = new PDO(
            $this->conn_string,
            $this->user,
            $this->pass
        );

        $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Proxy the calls through to the PDO object
     * @param  string $method Method dane
     * @param  array  $params  The lsit of params
     * @return mixed
     */
    public function __call($method, $params)
    {
        // Make sure we have an array
        $params = (is_array($params) ? $params : [$params]);
        do {
            try {
                return call_user_func_array([$this->pdo, $method], $params);
            } catch (\PDOException $e) {
                if (
                    in_array($e->getCode(), $this->reconnect_on)
                ) {
                    $this->connect();
                    $try = (isset($try) ? $try + 1 : 1);
                    echo "retrying\n";
                } else {
                    throw $e;
                }
            }
        } while ($try < 2);
    }

    public function getPDO() : ?PDO
    {
        return (isset($this->pdo) ? $this->pdo : null);
    }
}
