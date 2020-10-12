<?php

require_once 'vendor/autoload.php';
use Google\Cloud\Logging\LoggingClient;

putenv('GOOGLE_APPLICATION_CREDENTIALS=unified-logging.json');
# putenv('GOOGLE_APPLICATION_CREDENTIALS=/etc/google/unified-logging.json');

$logging = new LoggingClient([
    'projectId' => 'unified-logging-292316'
]);

$gp_logger = $logging->psrLogger('pharmacy-automation');
$gp_logger = LoggingClient::psrBatchLogger('pharmacy-automation');
