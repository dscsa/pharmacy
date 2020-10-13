<?php

require_once 'dbs/mysql_wc.php';
require_once 'helper_logger.php';

global $mysql;
$log_notices = [];

function log_to_db($severity, $text, $file, $vars) {
   global $mysql;
   $text  = substr($text, 0, 255);
   $mysql = $mysql ?: new Mysql_Wc();
   $text  = $mysql->escape($text);
   $vars  = $mysql->escape($vars) ?: '[]';
   //$mysql->run("INSERT INTO gp_logs (severity, text, file, vars) VALUES ('$severity', '$text', '$file', '$vars')");
}

function email($email, $subject, $body) {

   if ( ! is_string($body))
    $body = json_safe_encode($body);

   mail($email, $subject, $body, "From: webform@goodpill.org\r\n");
}

function log_to_email($severity, $text, $file, $vars) {
   email(DEBUG_EMAIL, "$severity: $text", "$severity: $text. $file vars: $vars");
}

function log_to_cli($severity, $text, $file, $vars) {
   echo "

   $severity: $text. $file vars: $vars";
}

/**
 * Sanitize the vars so we don't expose server level details.  Convert the
 * passed in data to a JSON string
 *
 * @param  array $vars  The data to sanitize
 * @param  string $file The file calling the function
 *
 * @return string       The cleaned up string
 */
function vars_to_json($vars, $file) {

   global $gp_logger;
   $non_user_vars = [
    "_COOKIE",
    "_ENV",
    "_FILES",
    "_GET",
    "_POST",
    "_REQUEST",
    "_SERVER",
    "_SESSION",
    "argc",
    "argv",
    "GLOBALS",
    "HTTP_RAW_POST_DATA",
    "HTTP_ENV_VARS",
    "HTTP_POST_VARS",
    "HTTP_GET_VARS",
    "HTTP_COOKIE_VARS",
    "HTTP_SERVER_VARS",
    "HTTP_POST_FILES",
    "http_response_header",
    "ignore",
    "php_errormsg",
    "context",
    "mysql",
    "mssql"
  ];

  if ( ! is_array($vars)) { //MySQl json does not accept plain strings
    $vars = [$vars];
  }

  $vars = array_reverse($vars, true); //Put most recent variables at the top of the email
  $diff = array_diff_key($vars, array_flip($non_user_vars));
  return json_safe_encode($diff);
}

function json_safe_encode($raw, $file = NULL) {

  $json = json_encode(utf8ize($raw), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); //| JSON_PARTIAL_OUTPUT_ON_ERROR

  if ( ! $json) {
    $json  = '{}';
    $error = json_last_error_msg();


    if (is_array($vars)) {
      $log_context = $vars;
    } else {
      $log_context = ["vars" => $vars];
    }

    $gp_logger->error("json_encode failed for logging {$error} : {$file}", $log_context);

    if ($error == 'Inf and NaN cannot be JSON encoded')
      $error .= serialize($vars); //https://levels.io/inf-nan-json_encode/ json_encode(unserialize(str_replace(array(‘NAN;’,’INF;’),’0;’,serialize($reply))));

    $file = $file ?: get_file();
    log_to_cli('ERROR', 'json_encode failed for logging', $file, $error);
    log_to_email('ERROR', 'json_encode failed for logging', $file, $error);
  }

  return str_replace('\n', '', $json);
}


/**
 * Clean up a string so it is utf8 complient
 *
 * @param  string|array $d The item to sanitize
 *
 * @return mixed The sanitized item
 *
 * SOURCE https://stackoverflow.com/questions/19361282/why-would-json-encode-return-an-empty-string
 * TODO in PHP 7.2+ use JSON_INVALID_UTF8_IGNORE instead
 */
function utf8ize($d) {
  if (is_array($d)) {
      foreach ($d as $k => $v) {
          $d[$k] = utf8ize($v);
      }
  } else if (is_string ($d)) {
      return utf8_encode($d);
  }
  return $d;
}

/**
 * Log an info.  Right now we are using This to store data in
 * the database and stackdriver.  Eventually we should be able to
 * strip that back to just stackdriver
 *
 * @param  string $text The Message for the alert
 * @param  array $vars  The context vars for the alert
 * @return void
 *
 * TODO Move this over so it uses logging levels instead of CLI switch
 */
function log_info($text, $vars = '') {

  global $argv;

  global $gp_logger;

  if ( ! in_array('log=info', $argv)) return;

  $file   = get_file();

  if (is_array($vars)) {
    $log_context = $vars;
  } else {
    $log_context = ["vars" => $vars];
  }

  // Log it before we make a string of the vars
  $gp_logger->info("{$text} : {$file}", $log_context);

  $vars   = $vars ? vars_to_json($vars, $file) : '';

  log_to_cli(date('Y-m-d H:i:s').' INFO', $text, $file, $vars);
  log_to_db('INFO', $text, $file, $vars);
}

/**
 * Log an error.  Right now we are using This to store data in
 * the database and email and stackdriver.  Eventually we should be able to
 * strip that back to just stackdriver
 *
 * @param  string $text The Message for the alert
 * @param  array $vars  The context vars for the alert
 * @return void
 *
 * TODO Move this over so it uses logging levels instead of CLI switch
 */
function log_error($text, $vars = '') {

  global $log_notices;
  global $gp_logger;

  $file   = get_file();

  if (is_array($vars)) {
    $log_context = $vars;
  } else {
    $log_context = ["vars" => $vars];
  }

  // Log it before we make a string of the vars
  $gp_logger->error("{$text} : {$file}", $log_context);

  $vars   = $vars ? vars_to_json($vars, $file) : '';

  $log_notices[] = date('Y-m-d H:i:s')." ERROR $text, file:$file, vars:$vars";

  log_to_cli(date('Y-m-d H:i:s').' ERROR', $text, $file, $vars);
  log_to_email('ERROR', $text, $file, $vars);
  log_to_db('ERROR', $text, $file, $vars);
}

function log_notices() {
  global $log_notices;
  return implode(",
  ", $log_notices);
}

/**
 * Log a notice.  Right now we are using This to store data in
 * the database and email and stackdriver.  Eventually we should be able to
 * strip that back to just stackdriver
 *
 * @param  string $text The Message for the alert
 * @param  array $vars  The context vars for the alert
 * @return void
 *
 * TODO Move this over so it uses logging levels instead of CLI switch
 */
function log_notice($text, $vars = '') {

  global $argv;
  global $log_notices;
  global $gp_logger;

  if ( ! in_array('log=notice', $argv) AND ! in_array('log=info', $argv)) return;

  $file   = get_file();

  // Log it before we make a string of the vars
  if (is_array($vars)) {
    $log_context = $vars;
  } else {
    $log_context = ["vars" => $vars];
  }

  $gp_logger->notice("{$text} : {$file}", $log_context);

  $vars   = $vars ? vars_to_json($vars, $file) : '';

  $log_notices[] = date('Y-m-d H:i:s')." NOTICE $text, file:$file, vars:$vars";

  log_to_cli(date('Y-m-d H:i:s').' NOTICE', $text, $file, $vars);
  //log_to_email('NOTICE', $text, $file, $vars);
  log_to_db('NOTICE', $text, $file, $vars);
}

/**
 * Find the filename of the currently leve of include
 *
 * @return string The name of the file that ran this function
 */
function get_file() {
  $trace = debug_backtrace(2, 3); //1st arge: exlude ["object"] AND ["args"], 2nd arg is a limit to how far back
  $index = count($trace) - 1;
  return $trace[$index]['function']."($index) in ".$trace[$index-1]['file']." on line #".$trace[$index-1]['line'];
}

/**
 * A simple timer object to keep track of elapsed time
 * @param  string $label A label to add to the timer
 * @param  int    $start A microtime that signifies whene the timeer started
 *
 * @return int    The number of Milliseconds that have passed since the timer was created
 */
function timer($label, &$start) {
  $start = $start ?: [microtime(true), microtime(true)];
  $stop  = microtime(true);

  $diff = "
  $label: ".ceil($stop-$start[0])." seconds of ".ceil($stop-$start[1])." total
  ";

  $start[0] = $stop;

  return $diff;
}
