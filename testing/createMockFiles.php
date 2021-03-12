<?php
require_once 'header.php';
require_once 'vendor/autoload.php';

use Google\Cloud\Logging\LoggingClient;


function constructQuery($message) {
  $logName = "projects/".LOGGING_PROJECT_ID."/logs/pharmacy-automation";
  return <<<EOT
    logName="$logName"
    jsonPayload.message="$message"
  EOT;
}

function writeLogToMock($message) {
  $log = new LoggingClient(['projectId' => LOGGING_PROJECT_ID]);
  $entries = $log->entries([
      'resourceName' => "projects/".LOGGING_PROJECT_ID,
      'filter' => constructQuery($message),
      'orderBy' => 'timestamp desc',
      'pageSize' => 1,
      'resultLimit' => 1
  ]);

  $entries->rewind();
    if(!$entries->valid()) {
      throw new Exception("No Element for this item");
    }
  $ele = $entries->current();

  $file = fopen("testing/mocks/$message.json", "w") or die("cannot open file $message");
  fwrite($file, json_encode($ele->info()['jsonPayload']['context']));
  fclose($file);
}

/**
 * Possible values to pass into this
 *
 * data-update-drugs
 * data-update-stock-by-month
 * data-update-patients-cp
 * data-update-patients-wc
 * data-update-rxs-single
 * data-update-orders-cp
 * data-update-orders-wc
 * data-update-order-items
 */
writeLogToMock('data-update-drugs');
writeLogToMock('data-update-stock-by-month');
writeLogToMock('data-update-patients-cp');
writeLogToMock('data-update-patients-wc');
writeLogToMock('data-update-rxs-single');
exit();
