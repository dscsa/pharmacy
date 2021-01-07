<?php

namespace Sirum\Logging;

use Google\Cloud\Logging\LoggingClient;

require_once 'helpers/helper_pagerduty.php';

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
            list($message, $context) = $args;
        } else {
            $message = $args;
            $context = [];
        }

        $log_level = self::getLoggingLevelByString($method);


        if ($log_level > 500) {
            $pd_query = 'logName="projects/unified-logging-292316/logs/pharmacy-automation"';
            $pd_data  = ['execution_id' => self::$exec_id];
            $pd_query .= "\n" . 'jsonPayload.execution_id="' . self::$exec_id . '"';


            if (!is_null(self::$subroutine_id)) {
                $pd_data['subroutine_id'] = self::$subroutine_id;
                $pd_query .= "\n" . 'jsonPayload.subroutine_id="' . self::$subroutine_id . '"';
            }

            $pd_query                = urlencode($pd_query);
            $st_start                = gmdate('Y-m-d\TH:i:s.v', time() - 600);
            $st_end                  = gmdate('Y-m-d\TH:i:s.v', time() + 600);
            $pd_query                .= ";timeRange={$st_start}Z%2F{$st_end}Z";
            $pd_url                  = "https://console.cloud.google.com/logs/query;query=";
            $pd_url                  .= $pd_query;
            $pd_data['incident_url'] = $pd_url;
            $pd_data['level']        = $method;

            if ($log_level == 800) {
                pd_high_priority($message, 'pharmacy-app-emergency', $pd_data);
            } elseif ($log_level >= 500 && $log_level < 800) {
                pd_low_priority($message, 'pharmacy-app-urgent', $pd_data);
            }
        }

        $context = ["context" => $context];

        $context['execution_id'] = self::$exec_id;

        if (!is_null(self::$subroutine_id)) {
            $context['subroutine_id'] = self::$subroutine_id;
        }

        try {
            self::$logger->$method($message, $context);
        } catch (\Exception $e) {
            // The logger is broken.  We need to recycle it.
            self::$logger->flush();
            self::resetLogger();
            self::$logger->alert(
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
    public static function getLogger($application = 'pharmacy-automation', $execution = null)
    {
        if (!isset(self::$logger) or is_null(self::$logger)) {
            self::$application_id = $application;

            $logging  = new LoggingClient(['projectId' => 'unified-logging-292316']);

            self::$logger = $logging->psrLogger(
                self::$application_id,
                [
                    'batchEnabled' => true,
                    'batchOptions' => [
                        'batchSize' => 100,
                        'callPeriod' => 2.0,
                        'numWorkers' => 2
                    ]
                ]
            );

            if (is_null($execution)) {
                $execution = uniqid();
            }

            self::$exec_id = $execution;

            self::$application_id = $application;
        }

        return self::$logger;
    }
}
