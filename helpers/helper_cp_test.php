<?php
require_once 'keys.php';
require_once 'helpers/helper_pagerduty.php';

/**
 * Test the connection to carepoint.  Alert pagerduty if connection fails
 * @return boolean  True if connection successful
 */
function cp_test() {
    try {
        $conn = new PDO("sqlsrv:server=" . MSSQL_CP_IP . ";database=cph", MSSQL_CP_USER, MSSQL_CP_PWD);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $results = $conn->query('select TOP 1 * from csom');
        $result = $results->fetch();
    } catch (Exception $e) {
        // notify PagerDuty
        pd_low_priority('Carepoint database cannot be reached.  Possible Internet Outage at pharmacy', 'cp-outage');
        return false;
    }

    unset($conn);
    unset($reults);

    return true;
}
