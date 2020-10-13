<?php

/**
 * Properly escape strings to make the mysql safe
 * @param  string $raw
 * @return string
 */
function escape_db_values($raw) {
  //@mysql_escape_string(stripslashes(trim($clean))) is removed from php 7 and mysqli version requires db connection
  //stripslashes only remove one slash so \\1 become \1 and we want to remove all
  return preg_replace("/(^|[^'])'([^']|$)/i", "$1''$2", str_replace(['\\', 'â€™'], ['',"'"], trim($raw)));
}

// Convert empty string to null or CP's <Not Specified> to NULL
/**
 * Convert empty string and <Not Specified> in to DB safe values
 *
 * @param  mixed $val     The value to clean
 * @param  mixed $default The default value to use
 *
 * @return NULL|sting     Either Null or the '' wrapped string for the value
 */
function clean_val(&$val, &$default = null) {

  $clean = $val; //Since passed by reference don't accidentally overwrite $val

  if (is_nully($clean)) {

    if (is_nully($default))
      return 'NULL';

    $clean = $default;
  }

  //Don't escape or wrap JSON
  if($clean[0] == '{' AND $clean[strlen($clean) - 1] == '}')
    return $clean;

  //StripSlashes meant to prevent double escaping string
  $clean = escape_db_values($clean);

  return "'$clean'";
}

/**
 * Check to see if a value is ~NULL based on predefined rule
 *
 * @param  mixed  $val Some value to test
 *
 * @return boolean Notice 0 and 0 equivelents don't evaluate to true
 */
function is_nully($val) {
  return ! isset($val) OR $val === '' OR $val === '<Not Specified>' OR $val === 'NULL';
}

/**
 * Clean and format a phone number
 *  * Remove any non numeric characters
 *  * Add the country code and make sure
 *  * Return NULL if the final number isn't 10 digits
 *
 * @param  mixed $phone The phone number to format
 *
 * @return null|string  The formatted string or null if there was
 *    a problem with the original value
 */
function clean_phone($phone) {
  $phone = preg_replace("/[^0-9]/", "", $phone);
  if ( ! $phone) return 'NULL';
  $country = $phone[0] == '1' ? 1 : 0;
  $phone = substr($phone, $country, $country+10);
  return strlen($phone) == 10 ? "'$phone'" : 'NULL';
}

/**
 * Loops over an object to perfor multiple possible actions.
 *
 *
 * @param  Array $rows              An array of data. Generally pulled
 *                                    straight from mysql
 * @param  callable|NULL $callback  If $callback is callable, we execute
 *                                    it with the specified data  The callable should
 *                                    take 2 parameters, the data and the position of
 *                                    the data in the raw array.
 * @return [type]           [description]
 */
function result_map(&$rows, $callback = null) {

  foreach( $rows as $i => $row ) {

    foreach( $row as $key => $val ) {
      $row[$key] = clean_val($val);
    }

    if (!is_null($callback)) {
      $new = ($callback($row, $i) ?: $row);
    } else {
      $new = $row;
    }

    //If we added new columns we need to save the keys
    //WARNING We must save the same columns every time (no ifs) otherwise key / val pairs will be mismatched
    $keys = isset($keys) ? $keys : array_keys($new);

    $rows[$i] = array_string($new);
  }

  return $keys;
}

function array_string($arr) {
  return "(".implode(', ', $arr).")";
}

/**
 * Make sure the row is the proper length
 *
 * @param  array $row  A row out of the database
 * @param  mixed $key  The key for the item we want to check
 * @param  int $min    The smallest valid length
 * @param  int $max    (Optional) The largest valid length.  If not set,
 *    the field must be an exact size
 *
 * @return void
 */
function assert_length(&$row, $key, $min, $max = null) {

  // Value is null so lets leave
  if ($row[$key] == 'NULL') return;

  $len = strlen($row[$key]);
  $max = $max ?: $min;

  // Value is within the min and max so don't do anything
  if ($len >= $min AND $len <= $max) return;

  // QUESTION:  if it doesn't match, do we want to set it to NULL?
  $row[$key] = 'NULL';
}
