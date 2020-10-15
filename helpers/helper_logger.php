<?php

require_once 'vendor/autoload.php';

use Google\Cloud\Logging\LoggingClient;

#putenv('GOOGLE_APPLICATION_CREDENTIALS=unified-logging.json');
putenv('GOOGLE_APPLICATION_CREDENTIALS=/etc/google/unified-logging.json');

/**
 * This is a simple logger that maintains a single instance of the cloud $logger
 * This should not be stored in other code, but for the short term it's here.
 */
class SirumLog {

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
   * Overide the static funciton and pass unknow methos into the logger.
   * We will do some cleanup to make sure the data is an array and we will add
   * the execution id so we can group the log message
   *
   * @param  string $method  A message for the log entry
   * @param  mixed  $args    Any context that needs to be passed into the log
   * @return void
   */
  public static function __callStatic($method, $args) {
    global $execution_details;

    if (!isset(self::$logger)) {
      self::getLogger();
    }

    list($message, $context) = $args;

    $context = ["context" => $context];

    $context['execution_id'] = self::$exec_id;

    self::$logger->$method($message, $context);
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
  public static function getLogger($application = 'pharmacy-automation', $execution = null) {
    if (!isset(self::$logger)) {
      $logging  = new LoggingClient([
          'projectId' => 'unified-logging-292316'
      ]);

      self::$logger = $logging->psrLogger($application);

      if (is_null($execution)) {
        $execution = uniqid();
      }

      self::$exec_id = $execution;
    }

    self::$logger = LoggingClient::psrBatchLogger($application);

    return self::$logger;
  }
}

/*
 * This shouldn't be here.  When this moves into a standalone class,
 * we will rework it.
 */
SirumLog::getLogger('pharmacy-automation');
