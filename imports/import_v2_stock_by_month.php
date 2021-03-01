<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

/**
 * IMport the last 3 months of stock
 * @return void
 */
function import_v2_stock_by_month() {

  $mysql = new Mysql_Wc();

  //Live Stock Uses Three Months so let's import all three just in case we cleared the table
  //We can optimize later if this is too slow by checking to see if previous months are missing
  import_stock_for_month(-2, $mysql);
  import_stock_for_month(-1, $mysql);
  import_stock_for_month(0, $mysql);
}

//$month_index is 0 for current month, -1 for last month, +1 for next month, etc.
/**
 * Pull in the stock from a specific month in the past.  The data will be added
 * to the database and nothing will be returned
 *
 * @param  int $month_index The number of month to start import.  Number is case
 *    sensative.
 *        * < 0 is past month
 *        * 0 is current monty
 *        * > 0 is future month
 *
 * @param   Mysql_Wc $mysql       A MySql connection object
 *
 * @return void
 */
function import_stock_for_month($month_index, $mysql) {

  $context = stream_context_create([
      "http" => [
          "header" => "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD),
          "ignore_errors" => true //not sure if we should do this but error log is filling up with HTTP 400 bad request PHP warnings  
      ]
  ]);

  //In 2019-12, we will store a row for the date 2019-12-01 with the entered qty for 2019-11 and the unexpired inventory for 2019-03.
  //This is a 4 month gap because we dispensed in 3 months with a 1 month buffer
  $curr = $month_index+1;
  $next = $month_index+3;
  $last = $month_index; //Current month is partial month and can throw off an average, but this is fixed in update_stock_by_month rather than here

  //https://stackoverflow.com/questions/1889758/getting-last-months-date-in-php
  $curr          = strtotime("first day of ".($curr > 0 ? "+$curr" : $curr)." months");
  $next          = strtotime("first day of ".($next > 0 ? "+$next" : $next)." months");
  $last          = strtotime("first day of ".($last > 0 ? "+$last" : $last)." months");

  $curr          = ["year" => date('Y', $curr), "month" => date('m', $curr)];
  $next          = ["year" => date('Y', $next), "month" => date('m', $next)];
  $last          = ["year" => date('Y', $last), "month" => date('m', $last)];

  $curr_query = "?start_key=[\"8889875187\",\"month\",\"$curr[year]\",\"$curr[month]\"]&end_key=[\"8889875187\",\"month\",\"$curr[year]\",\"$curr[month]\",{}]&group_level=5";
  $next_query = "?start_key=[\"8889875187\",\"month\",\"$next[year]\",\"$next[month]\"]&end_key=[\"8889875187\",\"month\",\"$next[year]\",\"$next[month]\",{}]&group_level=5";
  $last_query = "?start_key=[\"8889875187\",\"month\",\"$last[year]\",\"$last[month]\"]&end_key=[\"8889875187\",\"month\",\"$last[year]\",\"$last[month]\",{}]&group_level=5";

  $inventory_url = V2_IP.':8443/transaction/_design/inventory-by-generic/_view/inventory-by-generic'.$next_query;
  $entered_url   = V2_IP.':8443/transaction/_design/entered-by-generic/_view/entered-by-generic'.$last_query;
  $verified_url  = V2_IP.':8443/transaction/_design/verified-by-generic/_view/verified-by-generic'.$last_query;
  $refused_url   = V2_IP.':8443/transaction/_design/refused-by-generic/_view/refused-by-generic'.$last_query;
  $expired_url   = V2_IP.':8443/transaction/_design/expired-by-generic/_view/expired-by-generic'.$last_query;
  $disposed_url  = V2_IP.':8443/transaction/_design/disposed-by-generic/_view/disposed-by-generic'.$last_query;
  $dispensed_url = V2_IP.':8443/transaction/_design/dispensed-by-generic/_view/dispensed-by-generic'.$last_query;

  $inventory     = file_get_contents($inventory_url, false, $context);
  $entered       = file_get_contents($entered_url, false, $context);
  $verified      = file_get_contents($verified_url, false, $context);
  $refused       = file_get_contents($refused_url, false, $context);
  $expired       = file_get_contents($expired_url, false, $context);
  $disposed      = file_get_contents($disposed_url, false, $context);
  $dispensed     = file_get_contents($dispensed_url, false, $context);

  if ( ! $inventory AND ! $entered AND ! $verified)
    return log_error('v2 Import Error: Inventory, Entered, & Verified', [$inventory_url, $entered_url, $verified_url]);

  if ( ! $inventory OR ! $entered OR ! $verified OR ! $refused OR ! $expired OR ! $disposed OR ! $dispensed)
    return;

  //email('import_v2_stock_by_month', V2_IP.'/transaction/_design/entered.qty-by-generic/_view/entered.qty-by-generic'.$last_query, $entered);

  $dbs = [
    'inventory' => json_decode($inventory, true)['rows'],
    'entered'   => json_decode($entered, true)['rows'],
    'verified'  => json_decode($verified, true)['rows'],
    'refused'   => json_decode($refused, true)['rows'],
    'expired'   => json_decode($expired, true)['rows'],
    'disposed'  => json_decode($disposed, true)['rows'],
    'dispensed' => json_decode($dispensed, true)['rows']
  ];

  foreach($dbs as $key => $rows) {

    $vals = [];

    foreach($rows as $row) {

      list($acct, $type, $year, $month, $drug_generic) = $row['key'];

      $val = [
        'drug_generic'  => "'$drug_generic'",
        'month'         => "'$curr[year]-$curr[month]-01'",  //First day of next month is proxy for last day of current month
        $key.'_sum'     => clean_val($row['value'][0]['sum']),
        $key.'_count'   => clean_val($row['value'][0]['count']),
        $key.'_min'     => clean_val($row['value'][0]['min']),
        $key.'_max'     => clean_val($row['value'][0]['max']),
        $key.'_sumsqr'  => clean_val($row['value'][0]['sumsqr'])
      ];

      if (
        ! $val[$key.'_sum'] OR
        ! $val[$key.'_count'] OR
        ! $val[$key.'_min'] OR
        ! $val[$key.'_max'] OR
        ! $val[$key.'_sumsqr']
      ) {
        log_error('v2 Stock Importing NULL', get_defined_vars());
        continue;
      }

      $vals[] = '('.implode(', ', $val).')';
    }

    //Rather than separate tables put into one table using ON DUPLICATE KEY UPDATE
    if ($vals)
      $mysql->run("
        INSERT INTO
          gp_stock_by_month_v2 (".implode(', ', array_keys($val)).")
        VALUES
          ".implode(', ', $vals)."
        ON DUPLICATE KEY UPDATE
          {$key}_sum    = VALUES({$key}_sum),
          {$key}_count  = VALUES({$key}_count),
          {$key}_min    = VALUES({$key}_min),
          {$key}_max    = VALUES({$key}_max),
          {$key}_sumsqr = VALUES({$key}_sumsqr)
      ");
  }
}
