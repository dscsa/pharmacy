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

  $order = file_get_contents(V2_IP.'/account/8889875187', false, $context);
  $order = json_decode($order, true);

  $drugs = file_get_contents(V2_IP.'/drug/_design/by-generic-gsns/_view/by-generic-gsns?group_level=3', false, $context);
  $drugs = json_decode($drugs, true);

  $vals = [];
  foreach($drugs['rows'] as $row) {

    list($drug_generic, $drug_brand, $drug_gsns) = $row['key'];
    list($price_goodrx, $price_nadac, $price_retail) = $row['value'];
    $o = isset($order['ordered'][$drug_generic]) ? $order['ordered'][$drug_generic] + $order['default'] : [];

    $val = [
      'drug_generic'      => "'$drug_generic'",
      'drug_brand'        => $drug_brand ? "'$drug_brand'" : 'NULL',
      'drug_gsns'         => $drug_gsns ? "',$drug_gsns,'" : 'NULL',   //Enclose with ,, so we can do DRUG_GSNS LIKE '%,GSN,%' and still match first and list in list
      'drug_ordered'      => $o ? 1 : 'NULL',
      'price30'           => get_order_by_key($o, 'price30'),
      'price90'           => get_order_by_key($o, 'price90'),
      'repack_qty'        => get_order_by_key($o, 'repackQty'),
      'min_qty'           => get_order_by_key($o, 'minQty'),
      'min_days'          => get_order_by_key($o, 'minDays'),
      'max_inventory'     => get_order_by_key($o, 'maxInventory'),
      'message_display'   => get_order_by_key($o, 'displayMessage'),
      'message_verified'  => get_order_by_key($o, 'verifiedMessage'),
      'message_destroyed' => get_order_by_key($o, 'destroyedMessage'),
      'price_goodrx'      => "'".($price_goodrx['sum']/$price_goodrx['count'])."'",
      'price_nadac'       => "'".($price_nadac['sum']/$price_nadac['count'])."'",
      'price_retail'      => "'".($price_retail['sum']/$price_retail['count'])."'",
      'count_ndcs'        => "'$price_goodrx[count]'"
    ];

    $vals[] = '('.implode(', ', $val).')';
  }

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_drugs_v2');

  $mysql->run("INSERT INTO gp_drugs_v2 (".implode(', ', array_keys($val)).") VALUES ".implode(', ', $vals));
}

function get_order_by_key($order, $key) {
  return isset($order[$key])? "'$order[$key]'" : 'NULL';
}
