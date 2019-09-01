<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_v2_drugs() {

  $mysql = new Mysql_Wc();

  $context = stream_context_create([
      "http" => [
          "header" => "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD)
      ]
  ]);

  $last = strtotime('-3 months');
  $next = strtotime('+3 months');
  $last = ["year" => date('Y', $last), "month" => date('m', $last)];
  $next = ["year" => date('Y', $next), "month" => date('m', $next)];
  $curr = ["year" => date('Y'), "month" => date('m')];

  $last = "?start_key=['8889875187','month','$last[year]','$last[month]']&end_key=['8889875187','month','$curr[year]','$curr[month]',{}]&group_level=5";
  $next = "?start_key=['8889875187','month','$curr[year]','$curr[month]']&end_key=['8889875187','month','$next[year]','$next[month]',{}]&group_level=5";
  $inventory = file_get_contents(V2_IP.'/transaction/_design/inventory.qty-by-generic/_view/inventory.qty-by-generic'.$next, false, $context);
  $entered  = file_get_contents(V2_IP.'/transaction/_design/entered.qty-by-generic/_view/entered.qty-by-generic'.$last, false, $context);
  $verified = file_get_contents(V2_IP.'/transaction/_design/verified.qty-by-generic/_view/verified.qty-by-generic'.$last, false, $context);
  $refused = file_get_contents(V2_IP.'/transaction/_design/refused.qty-by-generic/_view/refused.qty-by-generic'.$last, false, $context);
  $expired = file_get_contents(V2_IP.'/transaction/_design/expired.qty-by-generic/_view/expired.qty-by-generic'.$last, false, $context);
  $disposed = file_get_contents(V2_IP.'/transaction/_design/disposed.qty-by-generic/_view/disposed.qty-by-generic'.$last, false, $context);
  $dispensed = file_get_contents(V2_IP.'/transaction/_design/dispensed.qty-by-generic/_view/dispensed.qty-by-generic'.$last, false, $context);

  $dbs = [
    'inventory' => json_decode($inventory, true)['rows'],
    'entered' => json_decode($entered, true)['rows'],
    'verified' => json_decode($verified, true)['rows'],
    'refused' => json_decode($refused, true)['rows'],
    'expired' => json_decode($expired, true)['rows'],
    'disposed' => json_decode($disposed, true)['rows'],
    'dispensed' => json_decode($dispensed, true)['rows']
  ]

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_stock_v2');


  foreach($dbs as $key => $rows) {

    $vals = [];

    foreach($rows as $row) {

      list($acct, $type, $year, $month, $drug_generic) = $row['key'];

      $val = [
        'drug_generic'  => "'$drug_generic'",
        'month'         => date_format(date_create_from_format('m/y', "$month/$year"), "'Y-m-d'"),
        $key.'_sum'     => "'$row[value][sum]'",
        $key.'_count'   => "'$row[value][count]'",
        $key.'_min'     => "'$row[value][min]'",
        $key.'_max'     => "'$row[value][max]'",
        $key.'_sumsqr'  => "'$row[value][sumsqr]'"
      ];

      $vals[] = '('.implode(', ', $val).')';
    }

    //Rather than separate tables put into one table using ON DUPLICATE KEY UPDATE
    $mysql->run("
      INSERT INTO
        gp_drugs_v2 (".implode(', ', array_keys($val)).")
      VALUES
        ".implode(', ', $vals)."
      ON DUPLICATE KEY UPDATE
        $key.'_sum'     = $key.'_sum',
        $key.'_count'   = $key.'_count',
        $key.'_min'     = $key.'_min',
        $key.'_max'     = $key.'_max',
        $key.'_sumsqr'  = $key.'_sumsqr'
    ");
  }
}
