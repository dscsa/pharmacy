<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';
require_once 'helpers/helper_v2.php';

function import_v2_drugs() {

  $mysql = new Mysql_Wc();

  $order = v2_fetch('/account/8889875187');
  $drugs = v2_fetch('/drug/_design/by-generic-gsns/_view/by-generic-gsns?group_level=3');

  if ( ! isset($order['ordered'], $order['default'], $drugs['rows']))
    return log_error('Aborting Import V2 Drugs', $order);

  $o = $order['ordered'];
  $d = $order['default'];

  //log_info("
  //import_v2_drugs: rows ".count($drugs['rows']));

  $vals = [];
  foreach($drugs['rows'] as $row) {

    list($drug_generic, $drug_gsns, $drug_brand) = $row['key'];
    list($price_goodrx, $price_nadac, $price_retail) = $row['value'];

    $val = [
      'drug_generic'      => "'$drug_generic'",
      'drug_brand'        => clean_val($drug_brand),
      'drug_gsns'         => $drug_gsns ? "',$drug_gsns,'" : 'NULL',   //Enclose with ,, so we can do DRUG_GSNS LIKE '%,GSN,%' and still match first and list in list
      'drug_ordered'      => isset($o[$drug_generic]) ? 1 : 'NULL',
      'price30'           => clean_val($o[$drug_generic]['price30'], $d['price30']),
      'price90'           => clean_val($o[$drug_generic]['price90'], $d['price90']),
      'qty_repack'        => clean_val($o[$drug_generic]['repackQty'], $d['repackQty']),
      'qty_min'           => clean_val($o[$drug_generic]['minQty'], $d['minQty']),
      'days_min'          => clean_val($o[$drug_generic]['minDays'], $d['minDays']),
      'max_inventory'     => clean_val($o[$drug_generic]['maxInventory'], $d['maxInventory']),
      'message_display'   => clean_val($o[$drug_generic]['displayMessage']),
      'message_verified'  => clean_val($o[$drug_generic]['verifiedMessage']),
      'message_destroyed' => clean_val($o[$drug_generic]['destroyedMessage']),
      'price_goodrx'      => "'".($price_goodrx['sum']/$price_goodrx['count'])."'",
      'price_nadac'       => "'".($price_nadac['sum']/$price_nadac['count'])."'",
      'price_retail'      => "'".($price_retail['sum']/$price_retail['count'])."'",
      'count_ndcs'        => "'$price_goodrx[count]'"
    ];

    $vals[] = '('.implode(', ', $val).')';
  }

  $mysql->replace_table("gp_drugs_v2", array_keys($val), $vals);

  $duplicates = $mysql->run("
    SELECT
      drug1.drug_gsns,
      GROUP_CONCAT(drug1.drug_generic) as drug_generics,
      COUNT(*) as number
    FROM gp_drugs_v2 drug1
    JOIN gp_drugs_v2 drug2
      ON drug1.drug_gsns = drug2.drug_gsns
    WHERE drug1.drug_generic != drug2.drug_generic
    GROUP BY drug_gsns HAVING number > 1
  ");

  if (count($duplicates[0])) {
    log_error('WARNING Duplicate GSNs in V2', $duplicates[0]);
  }
}
