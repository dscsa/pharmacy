<?php

require_once 'vendor/autoload.php';

#putenv('GOOGLE_APPLICATION_CREDENTIALS=unified-logging.json');
putenv('GOOGLE_APPLICATION_CREDENTIALS=/etc/google/unified-logging.json');

use Sirum\Logging\SirumLog;

/*
 * This shouldn't be here.  When this moves into a standalone class,
 * we will rework it.
 */
SirumLog::getLogger('dev-pharmacy-automation');
