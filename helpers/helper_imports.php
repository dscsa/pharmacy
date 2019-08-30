<?php

// Convert empty string to null or CP's <Not Specified> to NULL
function clean_val($val) {
  $val = mysql_real_escape_string(trim($val));
  return ($val === '' OR $val === '<Not Specified>') ? 'NULL' : '"'.$val.'"';
}

//2d array map
function result_map($rows, $row_cb, $col_cb = null) {
  foreach( $rows as $i => $row ) {
    foreach( $row as $key => $val ) {
      $new = $col_cb AND $col_cb($key, $val);
      if ( ! is_null($new)) $row[$key] = $new;
    }
    $new = $row_cb AND $row_cb($i, $row);
    if ( ! is_null($new)) $rows[$i] = $new;
  }
  return $rows;
}

function sort_cols($row) {
  ksort($row); //by reference, no return value
  return implode(', ', $row);
}
