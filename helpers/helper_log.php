<?php

require_once 'dbs/mysql_wc.php';

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

function log_to_email($severity, $text, $file, $vars) {
   mail(DEBUG_EMAIL, "$severity: $text", "$severity: $text. $file vars: $vars", "From: webform@goodpill.org\r\n");
}

function log_to_cli($severity, $text, $file, $vars) {
   echo "

   $severity: $text. $file vars: $vars";
}

function vars_to_json($vars, $file) {

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
  $json = json_encode(utf8ize($diff), JSON_PRETTY_PRINT); //| JSON_PARTIAL_OUTPUT_ON_ERROR

  if ( ! $json) {
    $error = json_last_error_msg();

    if ($error == 'Inf and NaN cannot be JSON encoded')
      $error .= serialize($vars); //https://levels.io/inf-nan-json_encode/ json_encode(unserialize(str_replace(array(‘NAN;’,’INF;’),’0;’,serialize($reply))));

    log_to_cli('ERROR', 'json_encode failed for logging', $file, $error);
    log_to_email('ERROR', 'json_encode failed for logging', $file, $error);
  }

  return $json ? str_replace('\n', '', $json) : '{}';
}

//https://stackoverflow.com/questions/19361282/why-would-json-encode-return-an-empty-string
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

function log_info($text, $vars = '') {

  global $argv;

  if ( ! in_array('log=info', $argv)) return;

  $file   = get_file();
  $vars   = $vars ? vars_to_json($vars, $file) : '';
  log_to_cli(date('Y-m-d H:i:s').' INFO', $text, $file, $vars);
  log_to_db('INFO', $text, $file, $vars);
}

function log_error($text, $vars = '') {

  global $log_notices;

  $file   = get_file();
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

function log_notice($text, $vars = '') {

  global $argv;
  global $log_notices;

  if ( ! in_array('log=notice', $argv) AND ! in_array('log=info', $argv)) return;

  $file   = get_file();
  $vars   = $vars ? vars_to_json($vars, $file) : '';

  $log_notices[] = date('Y-m-d H:i:s')." NOTICE $text, file:$file, vars:$vars";

  log_to_cli(date('Y-m-d H:i:s').' NOTICE', $text, $file, $vars);
  //log_to_email('NOTICE', $text, $file, $vars);
  log_to_db('NOTICE', $text, $file, $vars);
}

function get_file() {
  $trace = debug_backtrace(2, 3); //1st arge: exlude ["object"] AND ["args"], 2nd arg is a limit to how far back
  $index = count($trace) - 1;
  return $trace[$index]['function']."($index) in ".$trace[$index-1]['file']." on line #".$trace[$index-1]['line'];
}

function timer($label, &$start) {
  $start = $start ?: [microtime(true), microtime(true)];
  $stop  = microtime(true);

  $diff = "
  $label: ".ceil($stop-$start[0])." seconds of ".ceil($stop-$start[1])." total
  ";

  $start[0] = $stop;

  return $diff;
}
