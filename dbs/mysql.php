<?php
use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

class Mysql
{
    protected static $static_conn_object;

    public function __construct($ipaddress, $username, $password, $db)
    {
        $this->ipaddress  = $ipaddress;
        $this->username   = $username;
        $this->password   = $password;
        $this->db         = $db;
        $this->connection = $this->connect();
    }

    public function getConnectionObj() {
        return self::$static_conn_object;
    }

    protected function connect()
    {
        if (!isset(self::$static_conn_object) || !self::$static_conn_object->ping()) {
            // test that it is there with a show status, if it's not
            // sqlsrv_configure("WarningsReturnAsErrors", 0);
            self::$static_conn_object = mysqli_connect(
                $this->ipaddress,
                $this->username,
                $this->password,
                $this->db
            );

            if (mysqli_connect_errno()) {
                $this->logError('Error Connection 1 of 2 ');

                self::$static_conn_object = mysqli_connect(
                    $this->ipaddress,
                    $this->username,
                    $this->password,
                    $this->db
                );

                if (mysqli_connect_errno()) {
                    $this->logError('Error Connection 2 of 2');
                    unset(self::$static_conn_object);
                    return false;
                }
            }
        }

        return self::$static_conn_object;
    }

    public function commit()
    {
        return mysqli_commit($this->connection);
    }

    public function transaction()
    {
        return mysqli_begin_transaction($this->connection);
    }

    public function rollback()
    {
        return mysqli_rollback($this->connection);
    }

    public function escape($var)
    {
        return mysqli_real_escape_string($this->connection, $var);
    }

    public function replace_table($table, $keys, $vals)
    {
        $this->testConnection();
        if (! $vals or ! count($vals)) {
            return log_error("No $table vals to Import", ['vals' => array_slice($vals, 0, 100, true), 'keys' => array_slice($keys, 0, 100, true)]);
        }

        if (! $keys or ! count($keys)) {
            return log_error("No $table keys to Import", ['vals' => array_slice($vals, 0, 100, true), 'keys' => array_slice($keys, 0, 100, true)]);
        }

        $keys = implode(', ', $keys);
        $sql  = "INSERT INTO $table ($keys) VALUES ".implode(', ', $vals);

        $this->transaction();
        $this->run("DELETE FROM $table");
        $this->run($sql);

        $error = mysqli_error($this->connection);

        $success = $this->run("SELECT * FROM $table")[0];

        if ($success) {
            GPLog::info("$table import was SUCCESSFUL", ['count' => count($vals), 'vals' => array_slice($vals, 0, 100, true), 'keys' => $keys]);
            return $this->commit();
        }

        $this->rollback();
        $this->logError(["$table import was ABORTED", $error, count($vals), array_slice($vals, 0, 100, true), array_slice($keys, 0, 100, true)]);

        GPLog::emergency("!!!TABLE IMPORT ERROR!!! {$table} {$error}");
        echo "


      TABLE IMPORT ERROR
      $error

      ";

        echo $sql;
    }

    public function run($sql, $debug = false)
    {

        $this->testConnection();
        $starttime = microtime(true);

        try {
            $stmt = mysqli_query($this->connection, $sql);

            if ($stmt === false) {
                $message = mysqli_error($this->connection);

                // Handle deadlocks and retry the command
                if (
                    strpos($message, 'Rerun') !== false
                    || strpos($message, 'Deadlock found when trying to get lock') !== false
                ) {
                    GPLog::debug(
                        'Retrying Query because of deadlock',
                        [
                            'stmt'    => $stmt,
                            'message' => $message,
                            'debug'   => $debug,
                            'sql'     => $sql
                        ]
                    );

                    $this->run($sql, $debug);
                } else {
                    GPLog::alert(
                        'Query failed',
                        [
                            'stmt'    => $stmt,
                            'message' => $message,
                            'debug'   => $debug,
                            'sql'     => $sql
                        ]
                    );
                }

                return;
            }

            $results = $this->_getResults($stmt, $sql, $debug);

            if ($debug) {
                GPLog::debug(
                    sprintf(
                        "%s recordsets, the first with %s rows in %s seconds",
                        count($results),
                        count($results[0]),
                        (microtime(true) - $starttime)
                    ),
                    get_defined_vars()
                );
            }

            return $results;
        } catch (Exception $e) {
            GPLog::alert(
                'Exception Thrown during query',
                [
                    'message' => $e->getMessage(),
                    'sql'     => $sql
                ]
            );
        }
    }

    public function _getResults($stmt, $sql, $debug)
    {
        $results = [];

        $results[] = $this->_getRows($stmt, $sql, $debug);

        //https://dev.mysql.com/doc/refman/5.5/en/mysql-next-result.html
        //do {
        //  $results[] = $this->_getRows($stmt, $sql);
        //} while (mysqli_next_result($stmt));

        return $results;
    }

    public function _getRows($stmt, $sql, $debug)
    {
        if (! isset($stmt->num_rows) or ! $stmt->num_rows) {
            if ($debug and strpos($sql, 'SELECT') !== false) {
                $this->logError(['No Rows', $stmt, $sql, $debug]);
            }
            return [];
        }

        $rows = [];
        while ($row = mysqli_fetch_array($stmt, MYSQLI_ASSOC)) {
            if ($debug and ! empty($row['Message'])) {
                $this->logError(['dbMessage', $row, $stmt, $sql, $data, $debug]);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function logError($error)
    {
        GPLog::alert("Debug MYSQL", $error);
    }

    public function prepare($sql)
    {
        return $this->connection->prepare($sql);
    }


    public function testConnection()
    {
        if (!self::$static_conn_object->ping()) {
            $this->connect();
        }
    }
}
