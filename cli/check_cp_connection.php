<?php
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'keys.php';
require_once 'helpers/helper_pagerduty.php';
require_once 'helpers/helper_cp_test.php';

cp_test();
