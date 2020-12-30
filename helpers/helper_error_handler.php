<?php

require_once 'helpers/helper_pagerduty.php';

function gpErrorHandler($errno, $errstr, $error_file, $error_line)
{
    if (in_array($errno, [E_ERROR, E_USER_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        $message = "Pharmacy App - PHP Error: [$errno] $errstr - $error_file:$error_line";
        pd_low_priority($message, 'php-error' . uniqid());
        error_log($message);
        exit(1);
    }
}

function gpShutdownHandler()
{
    $lasterror  = error_get_last();
    $errno      = $lasterror['type'];
    $errstr     = $lasterror['message'];
    $error_file = $lasterror['message'];
    $error_line = $lasterror['line'];

    if (in_array($errno, [E_ERROR, E_USER_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        $message = "Pharmacy App - PHP Error: [{$errno}] $errstr - $error_file:$error_line";
        pd_low_priority($message, 'php-shutdown-error' . uniqid());
        error_log($message);
    }
}

function gpExceptionHandler($e)
{
    $message = "Pharmacy App - Uncaught Exception ";
    $message .= $e->getCode() . " " . $e->getMessage() ." ";
    $message .= $e->getFile() . ":" . $e->getLine() . "\n";
    $message .= $e->getTraceAsString();
    pd_low_priority($message, 'php-exception' . uniqid());
    error_log($message);
    exit(1);
}

set_error_handler("gpErrorHandler", E_ERROR|E_USER_ERROR|E_PARSE|E_COMPILE_ERROR);
set_exception_handler('gpExceptionHandler');
register_shutdown_function("gpShutdownHandler");
