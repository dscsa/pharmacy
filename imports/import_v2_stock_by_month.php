<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_v2_stock_by_month() {

  $mysql = new Mysql_Wc();

  $context = stream_context_create([
      "http" => [
          "header" => "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD)
      ]
  ]);

  $next = strtotime('+4 months');
  $next = ["year" => date('Y', $next), "month" => date('m', $next)];
  $curr = ["year" => date('Y'), "month" => date('m')];

  $curr_query = "?start_key=[\"8889875187\",\"month\",\"$curr[year]\",\"$curr[month]\"]&end_key=[\"8889875187\",\"month\",\"$curr[year]\",\"$curr[month]\",{}]&group_level=5";
  $next_query = "?start_key=[\"8889875187\",\"month\",\"$next[year]\",\"$next[month]\"]&end_key=[\"8889875187\",\"month\",\"$next[year]\",\"$next[month]\",{}]&group_level=5";
  $inventory = file_get_contents(V2_IP.'/transaction/_design/inventory.qty-by-generic/_view/inventory.qty-by-generic'.$next_query, false, $context);
  $entered  = file_get_contents(V2_IP.'/transaction/_design/entered.qty-by-generic/_view/entered.qty-by-generic'.$curr_query, false, $context);
  $verified = file_get_contents(V2_IP.'/transaction/_design/verified.qty-by-generic/_view/verified.qty-by-generic'.$curr_query, false, $context);
  $refused = file_get_contents(V2_IP.'/transaction/_design/refused.qty-by-generic/_view/refused.qty-by-generic'.$curr_query, false, $context);
  $expired = file_get_contents(V2_IP.'/transaction/_design/expired.qty-by-generic/_view/expired.qty-by-generic'.$curr_query, false, $context);
  $disposed = file_get_contents(V2_IP.'/transaction/_design/disposed.qty-by-generic/_view/disposed.qty-by-generic'.$curr_query, false, $context);
  $dispensed = file_get_contents(V2_IP.'/transaction/_design/dispensed.qty-by-generic/_view/dispensed.qty-by-generic'.$curr_query, false, $context);

  email('import_v2_stock_by_month', V2_IP.'/transaction/_design/inventory.qty-by-generic/_view/inventory.qty-by-generic'.$next_query, $inventory);

  $dbs = [
    'inventory' => json_decode($inventory, true)['rows'],
    'entered' => json_decode($entered, true)['rows'],
    'verified' => json_decode($verified, true)['rows'],
    'refused' => json_decode($refused, true)['rows'],
    'expired' => json_decode($expired, true)['rows'],
    'disposed' => json_decode($disposed, true)['rows'],
    'dispensed' => json_decode($dispensed, true)['rows']
  ];

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_stock_by_month_v2');

  foreach($dbs as $key => $rows) {

    $vals = [];

    email('import_v2_stock_by_month', $key, count($rows));


    //if ($key == 'entered')
    //  log_info("\n   import_v2_stock_by_month: rows ".count($rows));

    foreach($rows as $row) {

      list($acct, $type, $year, $month, $drug_generic) = $row['key'];

      $val = [
        'drug_generic'  => "'$drug_generic'",
        'month'         => "'$curr[year]-$curr[month]-01'",
        $key.'_sum'     => clean_val($row['value']['sum']),
        $key.'_count'   => clean_val($row['value']['count']),
        $key.'_min'     => clean_val($row['value']['min']),
        $key.'_max'     => clean_val($row['value']['max']),
        $key.'_sumsqr'  => clean_val($row['value']['sumsqr'])
      ];

      $vals[] = '('.implode(', ', $val).')';
    }

    //Rather than separate tables put into one table using ON DUPLICATE KEY UPDATE
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
