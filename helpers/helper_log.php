<?php

function log_info($msg = null) {

  if (isset($_ENV['SSH_CLIENT'])) {
    echo $msg;
    return;
  }

  if ( ! isset($_SERVER['webform_log'])) {
    $_SERVER['webform_log'] = [];
  }

  if (isnull($msg)) {
    return implode('\n', $_SERVER['webform_log']);
  }

  $_SERVER['webform_log'][] = $msg;
}
