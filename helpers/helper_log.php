<?php

require_once 'dbs/mysql_wc.php';
require_once 'helper_logger.php';

use Sirum\Logging\SirumLog;

global $mysql;
$log_notices = [];

function email($email, $subject, $body)
{
    if (! is_string($body)) {
        $body = json_safe_encode($body);
    }

    mail($email, $subject, $body, "From: webform@goodpill.org\r\n");
}

function log_to_cli($severity, $text, $file, $vars)
{
    // echo "$severity: $text. $file vars: $vars\n";
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
function vars_to_json($vars, $file)
{
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

    if (! is_array($vars)) { //MySQl json does not accept plain strings
        $vars = [$vars];
    }

    $vars = array_reverse($vars, true); //Put most recent variables at the top of the email
    $diff = array_diff_key($vars, array_flip($non_user_vars));
    return json_safe_encode($diff);
}

function json_safe_encode($raw, $file = null)
{
    $json = json_encode(utf8ize($raw), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); //| JSON_PARTIAL_OUTPUT_ON_ERROR

    if (! $json) {
        $json  = '{}';
        $error = json_last_error_msg();


        if (is_array($vars)) {
            $log_context = $vars;
        } else {
            $log_context = ["vars" => $vars];
        }

        SirumLog::error("json_encode failed for logging {$error} : {$file}", $log_context);

        /*
         * https://levels.io/inf-nan-json_encode/
         * json_encode(
         *    unserialize(
         *        str_replace(
         *            array(‘NAN;’,’INF;’),
         *            ’0;’,
         *           serialize($reply))));
         */

        if ($error == 'Inf and NaN cannot be JSON encoded') {
            $error .= serialize($vars);
        }

        $file = $file ?: get_file();
        log_to_cli('ERROR', 'json_encode failed for logging', $file, $error);
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
function utf8ize($d)
{
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } elseif (is_string($d)) {
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
function log_info($text, $vars = '')
{
    global $argv;

    global $gp_logger;

    if (! in_array('log=info', $argv)) {
        return;
    }

    $file   = get_file();

    if (is_array($vars)) {
        $log_context = $vars;
    } else {
        $log_context = ["vars" => $vars];
    }

    // Log it before we make a string of the vars
    SirumLog::info("{$text} : {$file}", $log_context);

    $vars   = $vars ? vars_to_json($vars, $file) : '';

    log_to_cli(date('Y-m-d H:i:s').' INFO', $text, $file, $vars);
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
function log_error($text, $vars = '')
{
    global $log_notices;
    global $gp_logger;

    $file   = get_file();

    if (is_array($vars)) {
        $log_context = $vars;
    } else {
        $log_context = ["vars" => $vars];
    }

    // Log it before we make a string of the vars
    SirumLog::error("{$text} : {$file}", $log_context);

    $vars   = $vars ? vars_to_json($vars, $file) : '';

    $log_notices[] = date('Y-m-d H:i:s')." ERROR $text, file:$file, vars:$vars";

    log_to_cli(date('Y-m-d H:i:s').' ERROR', $text, $file, $vars);
}

function log_notices()
{
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
function log_notice($text, $vars = '')
{
    global $argv;
    global $log_notices;
    global $gp_logger;

    if (! in_array('log=notice', $argv) and ! in_array('log=info', $argv)) {
        return;
    }

    $file   = get_file();

    // Log it before we make a string of the vars
    if (is_array($vars)) {
        $log_context = $vars;
    } else {
        $log_context = ["vars" => $vars];
    }

    SirumLog::notice("{$text} : {$file}", $log_context);

    $vars   = $vars ? vars_to_json($vars, $file) : '';

    $log_notices[] = date('Y-m-d H:i:s')." NOTICE $text, file:$file, vars:$vars";

    log_to_cli(date('Y-m-d H:i:s').' NOTICE', $text, $file, $vars);
}

/**
 * Find the filename of the currently leve of include
 *
 * @return string The name of the file that ran this function
 */
function get_file()
{
    $trace = debug_backtrace(2, 3); //1st arge: exlude ["object"] AND ["args"], 2nd arg is a limit to how far back
    $index = count($trace) - 1;
    return $trace[$index]['function']."($index) in ".$trace[$index-1]['file']." on line #".$trace[$index-1]['line'];
}

function log_timer($label, $start_time, $count) {
  global $global_exec_details;

  $total_time   = ceil(microtime(true) - $start_time);
  $average_time = $count ? ceil($total_time/$count) : null;

  $global_exec_details['timers_loops']["$label-total"]   = $total_time;
  $global_exec_details['timers_loops']["$label-count"]   = $count;
  $global_exec_details['timers_loops']["$label-average"] = $average_time;

  if ($average_time > 30)
    SirumLog::alert(
      "helper_log log_timer: $label has long average time loop time of $average_time seconds",
      [
        'start_time'          => $start_time,
        'count'               => $count,
        '$total_time'         => $total_time,
        '$average_time'       => $average_time,
        'global_exec_details' => $global_exec_details
      ]
    );
}
