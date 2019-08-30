<?php

// Convert empty string to null or CP's <Not Specified> to NULL
function clean_val($val) {
  $val = mysql_real_escape_string(trim($val));
  return ($val === '' OR $val === '<Not Specified>') ? 'NULL' : '"'.$val.'"';
}

//2d array map
function result_map($rows, $callback) {
  foreach( $rows as $i => $row ) {
    foreach( $row as $key => $val ) {
      $row[$key] = clean_val($val);
    }
    $new = $callback($row, $i);
    if ( ! is_null($new)) $rows[$i] = $new;
  }
  return $rows;
}

function sort_cols($row) {
  ksort($row); //by reference, no return value
  return implode(', ', $row);
}
