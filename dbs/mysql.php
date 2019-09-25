<?php

class Mysql {

   function __construct($ipaddress, $username, $password, $db){
      $this->ipaddress  = $ipaddress;
      $this->username   = $username;
      $this->password   = $password;
      $this->db         = $db;
      $this->connection = $this->_connect();
   }

   function _connect() {

        //sqlsrv_configure("WarningsReturnAsErrors", 0);
        $conn = mysqli_connect($this->ipaddress, $this->username, $this->password, $this->db);

        if ( ! is_resource($conn)) {
            $this->_emailError('Error Connection 1 of 2');

            $conn = mysqli_connect($this->ipaddress, $this->username, $this->password, $this->db);

            if ( ! is_resource($conn)) {
              $this->_emailError('Error Connection 2 of 2');
              return false;
            }
        }

        //mysqli_select_db($conn, $this->db) ?: $this->_emailError('Could not select database '.$this->db);
        return $conn;
    }

    function run($sql, $debug = false) {

        $starttime = microtime(true);

        try {
          $stmt = mysqli_query($this->connection, $sql);
        }
        catch (Exception $e) {
          $this->_emailError('SQL Error', $e->getMessage(), $sql, $debug);
        }

        if ($stmt === false) {

          $message = mysqli_error($this->connection);

          //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
          if (strpos($message, 'Rerun') !== false) {
            $this->run($sql, $debug); //Recursive
          }

          $this->_emailError('No Resource', $stmt, $message, $sql, $debug);

          return;
        }

        $results = $this->_getResults($stmt, $sql, $debug);

        if ($debug)
          log_info(count($results)." recordsets, the first with ".count($results[0])." rows in ".(microtime(true) - $starttime)." seconds: ".substr($sql, 0, 30));

        return $results;
    }

    function _getResults($stmt, $sql, $debug) {

        $results = [];

        $results[] = $this->_getRows($stmt, $sql, $debug);

        //https://dev.mysql.com/doc/refman/5.5/en/mysql-next-result.html
        //do {
        //  $results[] = $this->_getRows($stmt, $sql);
        //} while (mysqli_next_result($stmt));

        return $results;
    }

    function _getRows($stmt, $sql, $debug) {

      if ( ! is_resource($stmt) OR ! mysqli_num_rows($stmt)) {
        if ($debug AND strpos($sql, 'SELECT') !== false)
          $this->_emailError('No Rows', $stmt, $sql, $debug);
        return [];
      }

      $rows = [];
      while ($row = mysqli_fetch_array($stmt, MYSQL_ASSOC)) {

          if ($debug AND ! empty($row['Message'])) {
            $this->_emailError('dbMessage', $row, $stmt, $sql, $data, $debug);
          }

          $rows[] = $row;
      }

      return $rows;
    }

    function _emailError() {
      $message = print_r(func_get_args(), true).' '.print_r(mysqli_connect_error(), true).' '.print_r(mysqli_error($this->connection), true);
      log_info("CRON: Debug MYSQL $message");
      mail('adam@sirum.org', "CRON: Debug MYSQL ", $message);
    }
}
