<?php

// Convert empty string to null or CP's <Not Specified> to NULL
function clean_val(&$val, &$default = null) {

  $clean = $val; //Since passed by reference don't accidentally overwrite $val

  if (is_nully($clean)) {

    if (is_nully($default))
      return 'NULL';

    $clean = $default;
  }

  $clean = @mysql_escape_string(trim($clean));
  return "'$clean'";
}

//Like Truthy or Falsey but for our data sources
function is_nully($val) {
  return ! isset($val) OR $val === '' OR $val === '<Not Specified>' OR $val === 'NULL';
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
      : $row;

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

  // log_info("
  //  Assert Length: $key => $row[$key] has length of $len but needs to be between $min and $max ".print_r($row, true));

  $row[$key] = 'NULL';
}
