<?php

namespace Sirum\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once 'helpers/helper_pagerduty.php';
require_once 'helpers/helper_identifiers.php';

/**
 * This is a simple logger that maintains a single instance of the cloud $logger
 * This should not be stored in other code, but for the short term it's here.
 */
class CliLog
{

  /**
   * Property to store the logger for reuse.  shoudl be a PSR-3 compatible logger
   */
    public static $logger;

    /**
     * Stores the execution id so logs from a single run can be consolidated
     * @var string
     */
    public static $exec_id;

    /**
     * Stores the execution id so logs from a single run can be consolidated
     * @var string
     */
    public static $subroutine_id = null;

    /**
     * The application ID for the google logger
     * @var string
     */
    public static $application_id;

    /**
     * The available levels
     * @var array
     */
    protected static $levels = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 300,
        'warning'   => 400,
        'error'     => 500,
        'critical'  => 600,
        'alert'     => 700,
        'emergency' => 800
    ];

    /**
     * Overide the static funciton and pass unknow methos into the logger.
     * We will do some cleanup to make sure the data is an array and we will add
     * the execution id so we can group the log message
     *
     * @param  string $method  A message for the log entry
     * @param  mixed  $args    Any context that needs to be passed into the log
     * @return void
     */
    public static function __callStatic($method, $args)
    {
        global $execution_details;

        if (!isset(self::$logger)) {
            self::getLogger();
        }

        if (is_array($args)) {
            $message = @$args[0];
            $context = @$args[1];
        } else {
            $message = $args;
            $context = [];
        }

        $ids     = self::findCriticalId($context);
        $context = [
            'ids' => $ids,
            'execution_id' => SirumLog::$exec_id
        ];

        if (!is_null(SirumLog::$subroutine_id)) {
            $context['subroutine_id'] = SirumLog::$subroutine_id;
        }

        // get the file location this was called from
        $backtrace           = debug_backtrace();
        $start               = reset($backtrace);
        $end                 = end($backtrace);
        $context['file']     = "{$start['file']} : {$start['line']}";
        $context['function'] = $end['function'];

        try {
            self::$logger->$method($message, $context);
        } catch (\Exception $e) {
        }
    }

    /**
     * Do our best to find the 4 key fields
     * @param  array $context Who Knows what it could be
     * @return array          Empty if we couldn't find anything
     */
    protected static function findCriticalId($context)
    {

        foreach (
            [
                @$context,
                @$context[0],
                @$context['order'],
                @$context['order'][0],
                @$context['item'],
                @$context['deleted'],
                @$context['created'],
                @$context['updated'],
                @$context['deleted'][0],
                @$context['created'][0],
                @$context['updated'][0],
                @$context['partial'],
                @$context['patient_or_order[i]']
             ] as $possible
         ) {
            if (isset($possible['invoice_number'])) {
                 $name_source = $possible;
                 break;
            }
        }

        // No need to continute.  There isn't a source of data
        if (!isset($name_source)) {
            return [];
        }

        $invoice_number = $name_source['invoice_number'];
        $first_name     = @$name_source['first_name'];
        $last_name      = @$name_source['last_name'];
        $birth_date     = @$name_source['birth_date'];

        //If we have an invoice but not a patient, we want to get those details
        if ( empty($birth_date) && !empty($invoice_number)) {
            $patient = getPatientByInvoice($invoice_number);

            if (!empty($patient)) {
                $first_name     = $patient['first_name'];
                $last_name      = $patient['last_name'];
                $birth_date     = $patient['birth_date'];
            }
        }

        return [
            'invoice_number' => $invoice_number,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'birth_date'     => $birth_date
        ];
    }

    /**
     * Get the logging numeric level via a string
     * @param  string $logging_level One ov the available levels
     * @return int
     */
    protected static function getLoggingLevelByString($logging_level)
    {
        return self::$levels[strtolower($logging_level)];
    }

    /**
     * A method to load an store the logger.  We will create a logging instance
     * and a execution id.
     *
     * @param  string $application The name of the application
     * @param  string $execution   (Optional) An id to group the log entries.  If
     *    one isn't passed, we will create one
     *
     * @return LoggingClient  A PSR-3 compatible logger
     */
    public static function getLogger()
    {
        if (!isset(self::$logger) or is_null(self::$logger)) {
            // create a log channel
            self::$logger = new Logger('pharmacy-app');
            self::$logger->pushHandler(new StreamHandler('/var/log/pharmacy_app.log', Logger::DEBUG));
            self::$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        }

        return self::$logger;
    }
}
