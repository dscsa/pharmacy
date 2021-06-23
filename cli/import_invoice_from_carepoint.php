<?php

ini_set('memory_limit', '-1');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');
define('CONSOLE_LOGGING', 1);

require_once 'vendor/autoload.php';
require_once 'keys.php';

use GoodPill\Storage\Carepoint;
use GoodPill\Storage\Goodpill;

function printHelp() {
    echo <<<EOF
php import_invoice_from_carepoint.php -i invoice_number
    -i invoice_number  The invoice we are trying to import
EOF;
    exit;
}

$args   = getopt("i:h", array());

if (isset($args['h'])) {
   printHelp();
}

if (!isset($args['i'])) {
    echo "-i is a required parameter\n";
    printHelp();
}

$invoice_number = $args['i'];

$gp = Goodpill::getConnection();
$cp = CarePoint::getConnection();

$cp_pdo = $cp->prepare("SELECT
      invoice_nbr as invoice_number,
      pat_id as patient_id_cp,
      ISNULL(liCount, 0) as count_items,
      ustate.name as order_source,
      CASE WHEN csom.ship_date IS NOT NULL AND ship.ship_date IS NULL THEN 'Dispensed' ELSE ostate.name END as order_stage_cp,
      CASE WHEN csom.ship_date IS NOT NULL AND ship.ship_date IS NULL THEN 'Dispensed' ELSE ostatus.descr END as order_status,
      ship_addr1 as order_address1,
      ship_addr2 as order_address2,
      ship_city as order_city,
      ship_state_cd as order_state,
      LEFT(ship_zip, 5) as order_zip,
      csom_ship.tracking_code as tracking_number,
      CONVERT(varchar, add_date, 20) as order_date_added,
      CONVERT(varchar, csom.ship_date, 20) as order_date_dispensed,
      CASE
        WHEN
          csom_ship.tracking_code IS NULL
          AND order_state_cn <> 60
        THEN
          ship.ship_date
        ELSE
          CONVERT(varchar, COALESCE(ship.ship_date, csom.ship_date), 20)
      END as order_date_shipped,
      CASE WHEN order_state_cn = 60 THEN CONVERT(varchar, chg_date, 20) ELSE NULL END as order_date_returned,
      CONVERT(varchar, chg_date, 20) as order_date_changed
    FROM csom
      LEFT JOIN cp_acct ON cp_acct.id = csom.acct_id
      LEFT JOIN csct_code as ostate on (ostate.ct_id = 5000 and (isnull(csom.order_state_cn,0) = ostate.code_num))
      LEFT JOIN csct_code as ustate on (ustate.ct_id = 5007 and (isnull(csom.order_category_cn,0) = ustate.code_num))
      LEFT JOIN csomstatus as ostatus on (csom.order_state_cn = ostatus.state_cn and csom.order_status_cn = ostatus.omstatus)
      LEFT JOIN (SELECT order_id, MAX(ship_date) as ship_date FROM CsOmShipUpdate GROUP BY order_id) ship ON csom.order_id = ship.order_id -- CSOM_SHIP didn't always? update the tracking number within the day so use CsOmShipUpdate which is what endicia writes
      LEFT JOIN csom_ship ON csom.order_id = csom_ship.order_id -- CsOmShipUpdate won't have tracking numbers that Cindy inputted manually
    WHERE
      invoice_nbr = :invoice_number");

$cp_pdo->bindValue(':invoice_number', $invoice_number, \PDO::PARAM_STR);

$cp_pdo->execute();

if (!$cp_invoice_data = $cp_pdo->fetch()) {
    echo "No invoice found\n";
    exit;
}

echo "Invoice found, confirming it doesn't exist in Goodpill data before inserting\n";

$gp_pdo = $gp->prepare(
    "SELECT *
        FROM gp_orders
        WHERE invoice_number = :invoice_number"
);

$gp_pdo->bindValue(':invoice_number', $invoice_number, \PDO::PARAM_INT);

$gp_pdo->execute();

if ($gp_order_exists = $gp_pdo->fetch()) {
    echo "Invoice already exists in gp_orders table. \n";
    exit;
}

echo "Invoice  not in gp_orders table. Inserting it now \n";

$sql = "INSERT
        INTO gp_orders
            (" . implode(', ', array_keys($cp_invoice_data)) . ")
        VALUES (" . implode(', ', preg_filter('/^/', ':', array_keys($cp_invoice_data))) .")";

$insert_pdo = $gp->prepare($sql);

foreach ($cp_invoice_data as $db_field => $db_value) {
    $bind_type = \PDO::PARAM_STR;
    if (is_int($db_value)) {
        $bind_type = \PDO::PARAM_INT;
    } elseif (is_null($db_value)) {
        $bind_type = \PDO::PARAM_NULL;
    }

    $insert_pdo->bindValue(':'.$db_field, $db_value, $bind_type);
}

$insert_pdo->execute();

if ($insert_pdo->rowCount() == 1) {
    echo "Order imported\n";
} elseif ($insert_pdo->rowCount() > 1) {
    echo "Something went wrong, too many orders imported\n";
} else {
    echo "Something went wrong, no order imported\n";
}
