<?php

function escape_vals($rows) {

  //https://stackoverflow.com/questions/16710800/implode-data-from-a-multi-dimensional-array
  $results = [];
  foreach( $rows as $row ) {
    foreach( $row as $key => $val ) {
      $row[$key] = $val === '' ? 'NULL' : '"'.mysql_real_escape_string(trim($val)).'"'; // convert empty string to null
    }
    $results[] = '('.implode(', ', $row).')';
  }

  return $results;
}
