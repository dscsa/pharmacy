<?php

namespace Sirum\Logging;

use Google\Cloud\Logging\LoggingClient;

/**
 * This is a simple logger that maintains a single instance of the cloud $logger
 * This should not be stored in other code, but for the short term it's here.
 */
class SirumLog
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

        list($message, $context) = $args;

        $context = self::sortContext($context);

        $context = ["context" => $context];

        $context['execution_id'] = self::$exec_id;

        if (!is_null(self::$subroutine_id)) {
            $context['subroutine_id'] = self::$subroutine_id;
        }

        try {
            self::$logger->$method($message, $context);
        } catch (\Exception $e) {
            // The logger is broken.  We need to recycle it.
            self::resetLogger();
            self::$logger->warning(
                'Logging Generated error',
                [
                     'message' => $message,
                     'level' => $method,
                     'error' => $e->getMessage()
                ]
            );
            self::$logger->$method($message);
        }
    }

    /**
     * sortContext will order by key the items in the context.  It will descend
     * into sub arrays to the specified level.  It will recursivly sort subarrays
     * until the specified depth.
     * @param  array   $context The context array
     * @param  integer $depth   (Optional) How many level deep to sort
     * @param  integer $level   (Optional)  The current level for tracking depth
     *     This should almost never be manually passed.  It is used to keep track
     *     of the recursion.
     * @return array  The sorted array
     */
    public static function sortContext($context, $depth = 4, $level = 0)
    {
        if (is_array($context)) {
            ksort($context);
            if ($level < $depth) {
                foreach ($context as $key => $item) {
                    $context[$key] = self::sortContext($item, $depth, $level + 1);
                }
            }
        }

        return $context;
    }

    /**
     * Set the subroutine ID to null
     * @return void
     */
    public static function resetSubroutineId()
    {
        self::$subroutine_id = null;
    }

    /**
     * Rebuild the logger.  Sometimes an error can cause the logger
     * to stop working.  This should trash the current logger and crated a new
     * logger object
     *
     * @return void
     */
    public static function resetLogger()
    {
        self::$logger = null;
        return self::getLogger(self::$application_id, self::$exec_id);
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
    public static function getLogger($application = 'pharmacy-automation', $execution = null)
    {
        if (!isset(self::$logger) or is_null(self::$logger)) {
            self::$application_id = $application;

            $logging  = new LoggingClient(['projectId' => 'unified-logging-292316']);

            self::$logger = $logging->psrLogger(self::$application_id);

            if (is_null($execution)) {
                $execution = uniqid();
            }

            self::$exec_id = $execution;

            self::$application_id = $application;
        }

        self::$logger = LoggingClient::psrBatchLogger(self::$application_id);

        return self::$logger;
    }
}
