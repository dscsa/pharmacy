<?php
use GoodPill\Logging\GPLog;
use GoodPill\Logging\AuditLog;
use GoodPill\Logging\CliLog;

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

    protected function connect()
    {
        if (!isset(self::$static_conn_object)) {
            //sqlsrv_configure("WarningsReturnAsErrors", 0);
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
            log_info("$table import was SUCCESSFUL", ['count' => count($vals), 'vals' => array_slice($vals, 0, 100, true), 'keys' => $keys]);
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
        $starttime = microtime(true);

        try {
            $stmt = mysqli_query($this->connection, $sql);

            if ($stmt === false) {
                $message = mysqli_error($this->connection);

                //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
                if (strpos($message, 'Rerun') !== false) {
                    $this->run($sql, $debug); //Recursive
                }

                $this->logError(['SQL No Resource Meta', $stmt, $message, $debug]);
                $this->logError(['SQL No Resource Query', $message, $sql]); //Character limit so this might not be logged

                return;
            }

            $results = $this->_getResults($stmt, $sql, $debug);

            if ($debug) {
                log_info(count($results)." recordsets, the first with ".count($results[0])." rows in ".(microtime(true) - $starttime)." seconds", get_defined_vars());
            }

            return $results;
        } catch (Exception $e) {
            $this->logError(['SQL Error Message', $e->getMessage(), $sql, $debug]);
            $this->logError(['SQL Error Query', $sql]); //Character limit so this might not be logged
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
}
