<?php

require_once 'vendor/autoload.php';


if (file_exists('/etc/google/unified-logging.json')) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=/etc/google/unified-logging.json');
} else if (file_exists('/goodpill/webform/unified-logging.json')) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=/goodpill/webform/unified-logging.json');
}


use Sirum\Logging\{
    SirumLog,
    AuditLog,
    CliLog
};

/*
 * This shouldn't be here.  When this moves into a standalone class,
 * we will rework it.
 */
SirumLog::getLogger('pharmacy-automation');
AuditLog::getLogger();
CliLog::getLogger();
