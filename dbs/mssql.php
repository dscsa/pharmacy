<?php

class Mssql
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
            self::$static_conn_object = mssql_connect(
                $this->ipaddress,
                $this->username,
                $this->password
            );

            if (! is_resource(self::$static_conn_object)) {
                $this->logError('Error Connection 1 of 2');

                self::$static_conn_object = mssql_connect(
                    $this->ipaddress,
                    $this->username,
                    $this->password
                );

                if (! is_resource(self::$static_conn_object)) {
                    $this->logError('Error Connection 2 of 2');
                    unset(self::$static_conn_object);
                    return false;
                }
            }
        }

        if (!mssql_select_db($this->db, self::$static_conn_object)) {
            $this->logError(['Could not select database ', $this->db]);
        }

        return self::$static_conn_object;
    }

    public function run($sql, $debug = false)
    {
        $starttime = microtime(true);

        try {
            $stmt = mssql_query($sql, $this->connection);


            if ($stmt === false) { //false for error, true for deletes/updates / resource for selects

                $message = mssql_get_last_message();

                //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
                if (strpos($message, 'Rerun') !== false) {
                    $this->run($sql, $debug); //Recursive
                }

                $this->logError(['No Resource', $stmt, $message, $sql, $debug]);

                return;
            }

            if (! is_resource($stmt)) {
                return;
            } //I think this means query was succesful but it was a DELETE or UPDATE

            $results = $this->_getResults($stmt, $sql, $debug);

            if ($debug) {
                log_info(count($results)." recordsets, the first with ".count($results[0])." rows in ".(microtime(true) - $starttime)." seconds", get_defined_vars());
            }

            return $results;
        } catch (Exception $e) {
            $this->logError(['SQL Error', $e->getMessage(), $sql, $debug]);
        }
    }

    public function _getResults($stmt, $sql, $debug)
    {
        $results = [];

        do {
            $results[] = $this->_getRows($stmt, $sql, $debug);
        } while (mssql_next_result($stmt));

        return $results;
    }

    public function _getRows($stmt, $sql, $debug)
    {
        if (! is_resource($stmt) or ! mssql_num_rows($stmt)) {
            if ($debug and strpos($sql, 'SELECT') !== false) {
                $this->logError(['No Rows', $stmt, $sql, $debug]);
            }
            return [];
        }

        $rows = [];
        while ($row = mssql_fetch_array($stmt, MSSQL_ASSOC)) {
            if ($debug and ! empty($row['Message'])) {
                $this->logError(['dbMessage', $row, $stmt, $sql, $data, $debug]);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function logError($error)
    {
        SirumLog::alert("Debug MSSQL", print_r($error, true));
    }

    public function prepare($sql)
    {
        return $this->connection->prepare($sql);
    }
}
