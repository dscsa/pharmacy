<?php

function escape_vals($rows) {

  //https://stackoverflow.com/questions/16710800/implode-data-from-a-multi-dimensional-array
  $results = [];
  foreach( $rows as $row ) {
    foreach( $row as $key => $val ) {
      $val = mysql_real_escape_string(trim($val));
      $row[$key] = ($val === '' OR $val === '<Not Specified>') ? 'NULL' : '"'.$val.'"'; // convert empty string to null
    }
    $results[] = '('.implode(', ', $row).')';
  }

  return $results;
}

function where_to_set_clause($where_clause) {
  return str_replace(['NOT ', '<=>', ' OR'], ['', '=', ','], $where_clause);
}

function where_to_select_clause($where_clause) {
  return str_replace(['NOT ', '<=> new.', ' OR'], ['', 'as old_', ','], $where_clause);
}
