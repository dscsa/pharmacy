<?php

require_once('mssql_interface.php');
require_once('mssql_status_log.php');
require_once(dirname(__DIR__) . '/helpers/helper_log.php');

class Mssql_New_Drivers implements Mssql_Interface
{

    private $ipaddress;
    private $username;
    private $password;
    private $db;
    /**
     * @var bool|PDO
     */
    private $connection;

    function __construct($ipaddress, $username, $password, $db)
    {
        $this->ipaddress = $ipaddress;
        $this->username = $username;
        $this->password = $password;
        $this->db = $db;
        $this->connection = $this->_connect();
    }

    function _connect()
    {
        global $conn;

        if ($conn instanceof PDO)
            return $conn;

        $conn = $this->db_establish_connection();

        if ($conn === false)
        {
            $this->_emailError('Error Connection 1 of 2');

            $conn = $this->db_establish_connection();

            if ($conn === false)
            {
                $this->_emailError('Error Connection 2 of 2');
                return false;
            }
        }

        return $conn;
    }

    function db_establish_connection()
    {
        try
        {
            $conn = new PDO('sqlsrv:server=' . $this->ipaddress . ';database=cph', $this->username, $this->password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            Mssql_Status_Log::log('PDO connected');
            return $conn;
        }
        catch (Exception $e)
        {
            Mssql_Status_Log::error($e->getMessage());
            return false;
        }
    }

    function run($sql, $debug = false)
    {
        $starttime = microtime(true);

        if($this->connection === false){
            $this->_emailError(['Unable to run sql, Unable to establish database connection', $sql]);
        }

        try
        {
            $stmt = $this->db_query($this->connection, $sql);

            if (!($stmt instanceof PDOStatement))
            {
                $message = $this->db_get_last_error_message();

                //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
                if (strpos($message, 'Rerun') !== false)
                {
                    $this->run($sql, $debug);
                }

                $this->_emailError(['No Resource', $stmt, $message, $sql, $debug]);

                return;
            }

            $results = $this->_getResults($stmt, $sql, $debug);

            if ($debug)
                log_info(count($results) . " recordsets, the first with " . count($results[0]) . " rows in " . (microtime(true) - $starttime) . " seconds", get_defined_vars());

            return $results;
        }
        catch (Exception $e)
        {
            $this->_emailError(['SQL Error', $e->getMessage(), $sql, $debug]);
        }
    }

    function _getResults(PDOStatement $stmt, $sql, $debug)
    {
        $results = [];

        //get all rowsets from stored procedure
        do
        {
            if ($stmt->columnCount() > 0)
            {
                $results[] = $this->_getRows($stmt, $sql, $debug);
            }
        } while ($stmt->nextRowset());

        return $results;
    }

    function _getRows(PDOStatement $stmt, $sql, $debug)
    {
        $data = [];

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


        if (is_array($rows))
        {
            foreach ($rows as $row)
            {
                if ($debug && isset($row['Message']) && !empty(trim($row['Message'])))
                {
                    $this->_emailError(['dbMessage', $row, $stmt, $sql, $data, $debug]);
                }

                $data[] = $row;
            }
        }

        if (count($data) === 0)
        {
            if ($debug AND strpos($sql, 'SELECT') !== false)
                $this->_emailError(['No Rows', $stmt, $sql, $debug]);
            return [];
        }

        return $data;
    }

    function _emailError($error)
    {
        echo "Debug MSSQL " . print_r($error, true);
        log_to_cli('ERROR', "CRON: Debug MSSQL", '', print_r($error, true));
        log_to_email('ERROR', "CRON: Debug MSSQL", '', print_r($error, true));
    }


//function parity with mssql_get_last_message so that we can minimize changes to existing code that
//made heavy use of mssql_get_last_message
    function db_get_last_error_message()
    {
        return Mssql_Status_Log::hasErrors() ? Mssql_Status_Log::getLastError() : '';
    }


    function db_query(PDO $conn, $sql)
    {
        $statement = $conn->query($sql);

        if ($statement !== false)
            $statement->setFetchMode(PDO::FETCH_ASSOC);

        return $statement;
    }
}

