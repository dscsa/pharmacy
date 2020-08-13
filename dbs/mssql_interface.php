<?php

interface Mssql_Interface{
    function _connect();

    function run($sql, $debug = false);

    function _getResults(PDOStatement $stmt, $sql, $debug);

    function _getRows(PDOStatement $stmt, $sql, $debug);

    function _emailError($error);
}