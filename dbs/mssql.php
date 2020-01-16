<?php

class Mssql {

   function __construct($ipaddress, $username, $password, $db){
      $this->ipaddress  = $ipaddress;
      $this->username   = $username;
      $this->password   = $password;
      $this->db         = $db;
      $this->connection = $this->_connect();
   }

   function _connect() {

        //sqlsrv_configure("WarningsReturnAsErrors", 0);
        $conn = mssql_connect($this->ipaddress, $this->username, $this->password);

        if ( ! is_resource($conn)) {
          $this->_emailError('Error Connection 1 of 2');

          $conn = mssql_connect($this->ipaddress, $this->username, $this->password);

          if ( ! is_resource($conn)) {
            $this->_emailError('Error Connection 2 of 2');
            return false;
          }
        }

        mssql_select_db($this->db, $conn) ?: $this->_emailError(['Could not select database ', $this->db]);
        return $conn;
    }

    function run($sql, $debug = false) {

        $starttime = microtime(true);

        try {
          $stmt = mssql_query($sql, $this->connection);
        }
        catch (Exception $e) {
          $this->_emailError(['SQL Error', $e->getMessage(), $sql, $debug]);
        }

        if ( ! is_resource($stmt)) {

          $message = mssql_get_last_message();

          //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
          if (strpos($message, 'Rerun') !== false) {
            $this->run($sql, $debug); //Recursive
          }

          $this->_emailError(['No Resource', $stmt, $message, $sql, $debug]);

          return;
        }

        $results = $this->_getResults($stmt, $sql, $debug);

        if ($debug)
          log_info(count($results)." recordsets, the first with ".count($results[0])." rows in ".(microtime(true) - $starttime)." seconds", get_defined_vars());

        return $results;
    }

    function _getResults($stmt, $sql, $debug) {

        $results = [];

        do {
          $results[] = $this->_getRows($stmt, $sql, $debug);
        } while (mssql_next_result($stmt));

        return $results;
    }

    function _getRows($stmt, $sql, $debug) {

      if ( ! is_resource($stmt) OR ! mssql_num_rows($stmt)) {
        if ($debug AND strpos($sql, 'SELECT') !== false)
          $this->_emailError(['No Rows', $stmt, $sql, $debug]);
        return [];
      }

      $rows = [];
      while ($row = mssql_fetch_array($stmt, MSSQL_ASSOC)) {

          if ($debug AND ! empty($row['Message'])) {
            $this->_emailError(['dbMessage', $row, $stmt, $sql, $data, $debug]);
          }

          $rows[] = $row;
      }

      return $rows;
    }

    function _emailError($error) {
      //$mssql_get_last_message = mssql_get_last_message();
      //Don't do database logging here as this could cause an infinite loop
      log_to_cli('ERROR', "CRON: Debug MSSQL", '', json_encode($error));
      log_to_email('ERROR', "CRON: Debug MSSQL", '', json_encode($error));
    }
}
