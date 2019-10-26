<?php

function log_info($msg = null) {

  if (isset($_ENV['SSH_CLIENT'])) {
    echo $msg;
    return;
  }

  if ( ! isset($_SERVER['webform_log'])) {
    $_SERVER['webform_log'] = [];
  }

  if (is_null($msg)) {
    return implode('\n', $_SERVER['webform_log']);
  }

  $_SERVER['webform_log'][] = $msg;
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
