<?php

function log($msg) {

  if (isset($_ENV['SSH_CLIENT'])) {
    echo $msg;
    return;
  }

  if (is_null($_SERVER['webform_log'])) {
    $_SERVER['webform_log'] = [];
  }

  if ($msg) {
    $_SERVER['webform_log'][] = $msg;
  } else {
    echo implode('\n', $_SERVER['webform_log']);
  }
}
