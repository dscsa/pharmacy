<?php

ini_set('memory_limit', '-1');
date_default_timezone_set('America/New_York');
define('CONSOLE_LOGGING', 1);

require_once 'vendor/autoload.php';
require_once 'header.php';
require_once 'helpers/helper_constants.php';
require_once 'testing/helpers.php';

use GoodPill\Logging\GPLog;

$updated = [
    'invoice_number' => ENTER_SOME_INVOICE_NUMBER,
];

GPLog::testing('Testing Log', ['updated' => $updated]);
