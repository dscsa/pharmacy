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

        mssql_select_db($this->db, $conn) ?: $this->_emailError('Could not select database '.$this->db);
        return $conn;
    }

    function run($sql, $debug = false) {

        try {
          $stmt = mssql_query($sql, $this->connection);
        }
        catch (Exception $e) {
          $this->_emailError('SQL Error', $e->getMessage(), $sql, $debug);
        }

        if ( ! is_resource($stmt) AND ($stmt !== true OR $debug )) {

          $message = mssql_get_last_message();

          $this->_emailError( $stmt === true ? 'dbQuery' : 'No Resource', $stmt, $message, $sql, $debug);

          //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
          if (strpos($message, 'Rerun the transaction') !== false)
            $this->run($sql, $debug); //Recursive

          return;
        }

        $results = $this->_getResults($stmt, $sql, $debug);

        return $results;
    }

    function _getResults($stmt, $sql, $debug) {

        $results = [];

        do {
          $results[] = $this->_getRows($stmt, $sql, $debug);
        } while (mssql_next_result($stmt));

        if ($debug) {
          $this->_emailError('_getResults', $stmt, $sql,$results);
        }

        return $results;
    }

    function _getRows($stmt, $sql, $debug) {

      if ( ! is_resource($stmt) OR ! mssql_num_rows($stmt)) {
        if ($debug) $this->_emailError('No Rows', $stmt, $sql, $debug);
        return [];
      }

      $rows = [];
      while ($row = mssql_fetch_array($stmt, MSSQL_ASSOC)) {

          if ($debug AND ! empty($row['Message'])) {
            $this->_emailError('dbMessage', $row, $stmt, $sql, $data, $debug);
          }

          $rows[] = $row;
      }

      return $rows;
    }

    function _emailError() {
      $message = print_r(func_get_args(), true).' '.print_r(mssql_get_last_message(), true);
      echo "CRON: Debug MSSQL $message";
      mail('adam@sirum.org', "CRON: Debug MSSQL ", $message);
    }
}
