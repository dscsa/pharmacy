<?php

function replace_empty_with_null($rows, $fields) {

  //https://stackoverflow.com/questions/16710800/implode-data-from-a-multi-dimensional-array
  $results = [];
  foreach( $rows as $row ) {
    foreach( $fields as $field) {
      $row[$field] = $row[$field] ? '"'.$row[$field].'"' : 'NULL';
    }
    $results[] = '('.implode(', ', $row).')';
  }

  return $results;
}
