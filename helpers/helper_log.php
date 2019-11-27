<?php
function args_to_string($args) {
  $body = '';
  foreach ($args as $arg) {
    $body .= print_r($arg, true).' | ';
  }
  return $body;
}

function log_all() {

  $log = args_to_string(func_get_args());

  echo $log;

  if ( ! isset($_SERVER['webform_log'])) {
    $_SERVER['webform_log'] = [];
  }

  if ( ! $log) {
    return implode('\n', $_SERVER['webform_log']);
  }

  $_SERVER['webform_log'][] = $log;
}

function log_info() {

  global $argv;

  if (in_array('log=info', $argv))
    return call_user_func_array("log_all", func_get_args());
}

function email($subject) {
  call_user_func_array("log_info", func_get_args());
  mail(DEBUG_EMAIL, print_r($subject, true), args_to_string(func_get_args()));
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
