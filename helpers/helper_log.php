<?php

global $mysql;

function log_to_db($severity, $text, $file, $vars) {
   global $mysql;
   $mysql = $mysql ?: new Mysql_Wc();
   $text = str_replace("'", "''", $text); //Escape single quotes
   $mysql->run("INSERT INTO gp_logs (severity, text, file, vars) VALUES ('$severity', '$text', '$file', '$vars')");
}

function log_to_email($severity, $text, $file, $vars) {
   mail(DEBUG_EMAIL, "$severity: $text", "$severity: $text. $file vars: $vars");
}

function log_to_cli($severity, $text, $file, $vars) {
   echo "
   $severity: $text. $file vars: $vars";
}

function vars_to_json($vars) {

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
    "mysql"
  ];

  $vars = array_diff_key($vars, array_flip($non_user_vars));
  return str_replace('\n', '', json_encode($vars));
}

function log_info($text, $vars) {

  global $argv;

  if ( ! in_array('log=info', $argv)) return;

  $file   = get_file();
  $vars   = vars_to_json($vars);
  log_to_cli('INFO', $text, $file, $vars);
  log_to_db('INFO', $text, $file, $vars);
}

function log_error($text, $vars) {
  $file   = get_file();
  $vars   = vars_to_json($vars);
  log_to_cli('ERROR', $text, $file, $vars);
  log_to_email('ERROR', $text, $file, $vars);
  log_to_db('ERROR', $text, $file, $vars);
}

function get_file() {
  $trace = debug_backtrace(2); //exlude ["object"] AND ["args"]
  $index = isset($trace[2]) ? 2 : 1;
  return $trace[$index]['function'].'() in '.$trace[$index-1]['file'];
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
