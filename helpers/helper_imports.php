<?php

// Convert empty string to null or CP's <Not Specified> to NULL
function clean_val(&$val, &$default = null) {

  if ( ! isset($val)) {

    if ( ! isset($default))
      return 'NULL';

    $val = $default;
  }

  $val = mysql_real_escape_string(trim($val));
  return ($val === '' OR $val === '<Not Specified>' OR $val === 'NULL') ? 'NULL' : "'$val'";
}

function clean_phone($phone) {
  $phone = preg_replace("/[^0-9]/", "", $phone);
  if ( ! $phone) return 'NULL';
  $country = $phone[0] == '1' ? 1 : 0;
  $phone = substr($phone, $country, $country+10);
  return "'$phone'";
}

//2d array map
function result_map(&$rows, $callback = null) {

  foreach( $rows as $i => $row ) {

    foreach( $row as $key => $val ) {
      $row[$key] = clean_val($val);
    }

    $new = $callback
      ? ($callback($row, $i) ?: $row)
      : $new = $row;

    //If we added new columns we need to save the keys
    //WARNING We must save the same columns every time (no ifs) otherwise key / val pairs will be mismatched
    $keys = isset($keys) ? $keys : array_keys($new);

    $rows[$i] = array_string($new);
  }

  $rows = implode(',', $rows);

  return array_string($keys);
}

function array_string($arr) {
  return "(".implode(', ', $arr).")";
}

function assert_length(&$row, $key, $min, $max = null) {

  if ($row[$key] == 'NULL') return;

  $len = strlen($row[$key]);
  $max = $max ?: $min;

  if ($len >= $min AND $len <= $max) return;

  //echo "
  //  Assert Length: $key => $row[$key] has length of $len but needs to be between $min and $max ".print_r($row, true);

  $row[$key] = 'NULL';
}
