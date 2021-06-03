<?php
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'keys.php';
require_once 'helpers/helper_pagerduty.php';
require_once 'helpers/helper_cp_test.php';

if (file_exists('/tmp/cp_connection_test.json')) {
    $cp_details = json_decode(file_get_contents('/tmp/last_ss_id.json'));
} else {
    $cp_details = (object) [
        'failures' => 0,
        'time' => null
    ];
}

if (cp_test(false)) {
    $cp_details->time = time();
    if ($cp_details->failures >= 3) {
        // We should clear the PD alarm
        $cp_details->failures = 0;
    }
} else {
    $cp_details->time = time();
    $cp_details->failures++;
    if ($cp_details->failures >= 3) {
        cp_test(true);
    }
}

file_put_contents('/tmp/cp_connection_test.json', json_encode($cp_details));
