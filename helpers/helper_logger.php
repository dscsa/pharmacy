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

  public static $logger;

  public static function __callStatic($method, $args) {
    if (!isset(self::$logger)) {
      self::getLogger();
    }

    list($message, $context) = $args;

    if (!is_array($context)) {
        $context = ["context" => $context];
    }

    self::$logger->$method($message, $context);
  }

  public static function getLogger($application = 'pharmacy-automation') {
    if (!isset(self::$logger)) {
      $logging  = new LoggingClient([
          'projectId' => 'unified-logging-292316'
      ]);

      self::$logger = $logging->psrLogger($application);
      self::$logger = LoggingClient::psrBatchLogger($application);
    }

    return self::$logger;
  }
}

SirumLog::getLogger('test-app');
