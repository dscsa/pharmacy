<?php

// Convert empty string to null or CP's <Not Specified> to NULL
function clean_val($val) {
  $val = mysql_real_escape_string(trim($val));
  return ($val === '' OR $val === '<Not Specified>') ? 'NULL' : '"'.$val.'"';
}

//2d array map
function result_map($rows, $row_cb, $col_cb) {
  foreach( $rows as $row ) {
    foreach( $row as $key => $val ) {
      $col_cb AND $col_cb($row, $key, $val);
    }
    $row_cb AND $row_cb($row);
  }
  return $rows;
}

function sort_cols($row);
  return implode(', ', ksort($row));
}
