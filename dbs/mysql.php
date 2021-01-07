<?php
use Sirum\Logging\SirumLog;

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

        if (mysqli_connect_errno()) {
            $this->_emailError('Error Connection 1 of 2 ');

            $conn = mysqli_connect($this->ipaddress, $this->username, $this->password, $this->db);

            if (mysqli_connect_errno()) {
              $this->_emailError('Error Connection 2 of 2');
              return false;
            }
        }

        //mysqli_select_db($conn, $this->db) ?: $this->_emailError(['Could not select database', $this->db]);
        return $conn;
    }

    function commit() {
      return mysqli_commit($this->connection);
    }

    function transaction() {
      return mysqli_begin_transaction($this->connection);
    }

    function rollback() {
      return mysqli_rollback($this->connection);
    }

    function escape($var) {
      return mysqli_real_escape_string($this->connection, $var);
    }

    function replace_table($table, $keys, $vals) {

      if ( ! $vals OR ! count($vals))
        return log_error("No $table vals to Import", ['vals' => array_slice($vals, 0, 100, true), 'keys' => array_slice($keys, 0, 100, true)]);

      if ( ! $keys OR ! count($keys))
        return log_error("No $table keys to Import", ['vals' => array_slice($vals, 0, 100, true), 'keys' => array_slice($keys, 0, 100, true)]);

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
      $this->_emailError(["$table import was ABORTED", $error, count($vals), array_slice($vals, 0, 100, true), array_slice($keys, 0, 100, true)]);

      SirumLog::emergency("!!!TABLE IMPORT ERROR!!! {$table} {$error}");
      echo "


      TABLE IMPORT ERROR
      $error

      ";

      echo $sql;
    }

    function run($sql, $debug = false) {

        $starttime = microtime(true);

      try {
        $stmt = mysqli_query($this->connection, $sql);

        if ($stmt === false) {

          $message = mysqli_error($this->connection);

          //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
          if (strpos($message, 'Rerun') !== false) {
            $this->run($sql, $debug); //Recursive
          }

          $this->_emailError(['SQL No Resource Meta', $stmt, $message, $debug]);
          $this->_emailError(['SQL No Resource Query', $message, $sql]); //Character limit so this might not be logged

          return;
        }

        $results = $this->_getResults($stmt, $sql, $debug);

        if ($debug)
          log_info(count($results)." recordsets, the first with ".count($results[0])." rows in ".(microtime(true) - $starttime)." seconds", get_defined_vars());

        return $results;
      }
      catch (Exception $e) {
        $this->_emailError(['SQL Error Message', $e->getMessage(), $sql, $debug]);
        $this->_emailError(['SQL Error Query', $sql]); //Character limit so this might not be logged
      }
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

      if ( ! isset($stmt->num_rows) OR ! $stmt->num_rows) {
        if ($debug AND strpos($sql, 'SELECT') !== false)
          $this->_emailError(['No Rows', $stmt, $sql, $debug]);
        return [];
      }

      $rows = [];
      while ($row = mysqli_fetch_array($stmt, MYSQLI_ASSOC)) {

        if ($debug AND ! empty($row['Message'])) {
          $this->_emailError(['dbMessage', $row, $stmt, $sql, $data, $debug]);
        }

        $rows[] = $row;
      }

      return $rows;
    }

    function _emailError($error) {
      //$mysqli_error = isset($this->connection) ? mysqli_connect_errno($this->connection).': '.mysqli_error($this->connection) : mysqli_connect_errno().': '.mysqli_connect_error();
      //Don't do database logging here as this could cause an infinite loop
      SirumLog::alert("Debug MYSQL", $error);
    }
}
