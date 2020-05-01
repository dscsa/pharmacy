<?php

//One-Time Temp Script to Fix one off errors in MSSQL

ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'dbs/mysql_wc.php';
require_once 'dbs/mssql_cp.php';

$mysql = new Mysql_Wc();
$mssql = new Mssql_Cp();

$rxs = $mysql->run("
  SELECT
    gp_rxs_single.rx_number,
    gp_order_items.stock_level_initial
  FROM
    gp_rxs_single
  JOIN
    gp_order_items ON gp_order_items.rx_number = gp_rxs_single.rx_number
  WHERE
    rx_message_key LIKE 'NO ACTION STANDARD FILL' AND
    stock_level_initial IS NOT NULL
")[0];

$count = count($rxs);

foreach ($rxs as $i => $rx) {
  if ($rx['stock_level_initial'] == 'HIGH SUPPLY')
    echo "$i of $count, $rx[rx_message_key] $rx[stock_level_initial] UPDATE cprx SET priority_cn = 218 WHERE script_no = $rx[rx_number]";

  if ($rx['stock_level_initial'] == 'LOW SUPPLY')
    echo "$i of $count, $rx[rx_message_key] $rx[stock_level_initial] UPDATE cprx SET priority_cn = 219 WHERE script_no = $rx[rx_number]";

  if ($rx['stock_level_initial'] == 'REFILL ONLY')
    echo "$i of $count, $rx[rx_message_key] $rx[stock_level_initial] UPDATE cprx SET priority_cn = 220 WHERE script_no = $rx[rx_number]";

  if ($rx['stock_level_initial'] == 'OUT OF STOCK')
    echo "$i of $count, $rx[rx_message_key] $rx[stock_level_initial] UPDATE cprx SET priority_cn = 221 WHERE script_no = $rx[rx_number]";

  if ($rx['stock_level_initial'] == 'ONE TIME')
    echo "$i of $count, $rx[rx_message_key] $rx[stock_level_initial] UPDATE cprx SET priority_cn = 222 WHERE script_no = $rx[rx_number]";
}
