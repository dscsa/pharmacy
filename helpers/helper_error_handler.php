<?php

require_once 'helpers/helper_pagerduty.php';

use Sirum\Logging\{
    SirumLog,
    AuditLog,
    CliLog
};

/**
 * Handle an error by sending it to pagerduty, stackdriver and error_logs
 * @param  int    $errno      The PHP Error Number
 * @param  string $errstr   Description of the error
 * @param  string $error_file The File Name
 * @param  int   $error_line the line number
 * @return void
 */
function gpErrorHandler($errno, $errstr, $error_file, $error_line)
{
    if (in_array($errno, [E_ERROR, E_USER_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        $message = sprintf(
            "Pharmacy App - PHP Error: [%s] %s - %s:%s",
            $errno,
            $errstr,
            $error_file,
            $error_line
        );
        pushToSirumLog($message);

        $data = [];

        try {
            $data['execution_id'] = SirumLog::$exec_id;

            if (!is_null(SirumLog::$subroutine_id)) {
                $data['subroutine_id'] = SirumLog::$subroutine_id;
            }
        } catch (\Exception $e) {
        }

        pd_high_priority($message, 'php-error' . uniqid(), $data);
        error_log($message);
        exit(1);
    }
}

/**
 * Use the shutdown function to send any error messages that we can't catch
 *  with set_error_handler
 * @return void
 */
function gpShutdownHandler()
{
    $lasterror  = error_get_last();

    if (in_array($lasterror['type'], [E_ERROR, E_USER_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        gpErrorHandler(
            $lasterror['type'],
            $lasterror['message'],
            $lasterror['file'],
            $lasterror['line']
        );
    }
}

/**
 * And exception handler for any uncaught exceptions
 * @param  Exception $e The thrown exception
 * @return void
 */
function gpExceptionHandler($e)
{
    $message = "Pharmacy App - PHP Uncaught Exception ";
    $message .= $e->getCode() . " " . $e->getMessage() ." ";
    $message .= $e->getFile() . ":" . $e->getLine() . "\n";
    $message .= $e->getTraceAsString();

    pushToSirumLog($message);

    $data = [];

    try {
        $data['execution_id'] = SirumLog::$exec_id;

        if (!is_null(SirumLog::$subroutine_id)) {
            $data['subroutine_id'] = SirumLog::$subroutine_id;
        }
    } catch (\Exception $e) {
    }

    pd_high_priority($message, 'php-exception' . uniqid(), $data);

    error_log($message);
    exit(1);
}

/**
 * A utility function for trying to push to SirumLog
 * @param  string $message The message to send
 * @return
 */
function pushToSirumLog($message)
{
    try {
        SirumLog::error($message);
        SirumLog::getLogger()->flush();
    } catch (\Exception $e) {
    }
}


/*
    Attach all the various handlers as needed
 */
set_error_handler("gpErrorHandler", E_ERROR|E_USER_ERROR|E_PARSE|E_COMPILE_ERROR);
register_shutdown_function("gpShutdownHandler");
set_exception_handler('gpExceptionHandler');
