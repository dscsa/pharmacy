<?php

function replace_empty_with_null($rows) {

  //https://stackoverflow.com/questions/16710800/implode-data-from-a-multi-dimensional-array
  $results = [];
  foreach( $rows as $row ) {
    foreach( $row as $key => $val ) {
      $row[$key] = $val ? '"'.mysql_real_escape_string(trim($val)).'"' : 'NULL';
    }
    $results[] = '('.implode(', ', $row).')';
  }

  return $results;
}
