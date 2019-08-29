<?php

function escape_vals($rows) {

  //https://stackoverflow.com/questions/16710800/implode-data-from-a-multi-dimensional-array
  $results = [];
  foreach( $rows as $row ) {
    foreach( $row as $key => $val ) {
      $row[$key] = empty($val) ? 'NULL' : '"'.mysql_real_escape_string(trim($val)).'"'; //empty converts empty string or null to null
    }
    $results[] = '('.implode(', ', $row).')';
  }

  return $results;
}
