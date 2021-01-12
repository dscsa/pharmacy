<?php

use Sirum\Logging\SirumLog;

function load_full_item($partial, $mysql, $overwrite_rx_messages = false) {

  if ( ! $partial['rx_number']) {
    log_error('ERROR get_full_item: missing rx_number', get_defined_vars());
    return [];
  }

  $item = get_full_item($mysql, $partial['rx_number'], @$partial['invoice_number']);

  if ( ! $item) {
    $sql1 = "SELECT * FROM gp_rxs_single WHERE rx_number = $partial[rx_number]";
    $res1 = $mysql->run($sql1)[0];

    $sql2 = "SELECT * FROM gp_order_items WHERE rx_number = $partial[rx_number]";
    $res2 = $mysql->run($sql2)[0];

    $sql3 = "SELECT * FROM gp_order_items WHERE invoice_number = $partial[invoice_number]";
    $res3 = $mysql->run($sql3)[0];

    $sql4 = "SELECT * FROM gp_orders WHERE invoice_number = $partial[invoice_number]";
    $res4 = $mysql->run($sql4)[0];

    $sql5 = "SELECT * FROM gp_patients WHERE patient_id_cp = $partial[patient_id_cp]";
    $res5 = $mysql->run($sql5)[0];

    $sql6 = "SELECT * FROM gp_rxs_grouped WHERE patient_id_cp = $partial[patient_id_cp]";
    $res6 = $mysql->run($sql6)[0];

    $sql7 = "SELECT * FROM gp_rxs_grouped WHERE rx_numbers LIKE '%,$partial[rx_number],%'";
    $res7 = $mysql->run($sql7)[0];

    log_error("load_full_item: no item!", [
      'sql1' => $sql1,
      'res1' => $res1,
      'sql2' => $sql2,
      'res2' => $res2,
      'sql3' => $sql3,
      'res3' => $res3,
      'sql4' => $sql4,
      'res4' => $res4,
      'sql5' => $sql5,
      'res5' => $res5,
      'sql6' => $sql6,
      'res6' => $res6,
      'sql7' => $sql7,
      'res7' => $res7,
      'partial' => $partial
    ]);

    return;
  }

  if (@$partial['invoice_number']) {
    $order = get_full_order($mysql, $partial['invoice_number']);
    log_warning("load_full_item: is_order?  can we replace [item] below", ['item' => $item, 'order' => $order]);
  }

  $full_item = add_full_fields([$item], $mysql, $overwrite_rx_messages)[0];

  return $full_item;
  //log_info("Get Full Item", get_defined_vars());
}

function get_full_item($mysql, $rx_number, $invoice_number = null) {

  if ($invoice_number) //E.g. if changing days_dispensed_actual NULL >>> 90, then this will be true and order will be shipped
    $past_orders = "gp_order_items.invoice_number = $invoice_number";
  else //If no invoice number specified only show current orders
    $past_orders = "gp_order_items.rx_dispensed_id IS NULL";


  $sql = "SELECT
              gp_rxs_single.rx_number
            WHERE
              gp_rxs_single.rx_number = {$rx_number}";

  $query = $mysql->run($sql)[0];
  $debug_details  = $query;

  SirumLog::alert("load_full_item:  Debug", ['debug' => $debug_details]);

  $sql = "SELECT
              *,
              gp_rxs_grouped.*,
              gp_orders.invoice_number,
              gp_order_items.invoice_number as dontuse_item_invoice,
              gp_orders.invoice_number as dontuse_order_invoice,
              0 as is_order,
              0 as is_patient,
              1 as is_item
            FROM
              gp_rxs_single
            JOIN gp_patients ON
              gp_rxs_single.patient_id_cp = gp_patients.patient_id_cp
            JOIN gp_rxs_grouped ON
              rx_numbers LIKE CONCAT('%,', gp_rxs_single.rx_number, ',%')
            -- might not have a match if no GSN match
            LEFT JOIN gp_stock_live ON
              gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
            -- choice to show any order_item from this rx_group and not just if
            -- this specific rx matches
            LEFT JOIN gp_order_items ON
              {$past_orders} AND
              rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
            LEFT JOIN gp_orders ON -- ORDER MAY HAVE NOT BEEN ADDED YET
              gp_orders.invoice_number = gp_order_items.invoice_number
            WHERE
              gp_rxs_single.rx_number = {$rx_number}";

  $query = $mysql->run($sql)[0];


  SirumLog::alert("load_full_item:  Query", ['debug' => $query]);


  if ( ! @$query[0][0]) {
    get_full_item_debug($mysql, $rx_number, $sql);
    return;
  }

  $item = $query[0][0];

  if ( ! $item['drug_generic']) {
    log_warning(($item['rx_gsn'] ? 'get_full_item: Add GSN to V2!' : 'get_full_item: Missing GSN!')." Invoice Number:$item[invoice_number] Drug:$item[drug_name] Rx:$item[rx_number] GSN:$item[rx_gsn] GSNS:$item[drug_gsns]", ['item' => $item, 'partial' => $partial, 'sql' => $sql]);
  }

  log_notice('get_full_item: success', ['sql' => $sql, 'query' => $query]);

  return $item;
}

function get_full_item_debug($mysql, $rx_number, $sql) {
  $sql1 = "
    SELECT
      *,
      gp_orders.invoice_number as has_gp_orders,
      gp_order_items.rx_number as has_gp_order_items,
      gp_rxs_grouped.rx_numbers as has_gp_rxs_grouped,
      gp_rxs_single.rx_number as has_gp_rxs_single,
      gp_patients.patient_id_cp as has_gp_patients,
      gp_stock_live.drug_generic as has_gp_stock_live
    FROM
      gp_order_items
    LEFT JOIN gp_rxs_grouped ON
      rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
    LEFT JOIN gp_rxs_single ON
      gp_order_items.rx_number = gp_rxs_single.rx_number
    LEFT JOIN gp_patients ON
      gp_rxs_single.patient_id_cp = gp_patients.patient_id_cp
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
    LEFT JOIN gp_orders ON -- ORDER MAY HAVE NOT BEEN ADDED YET
      gp_orders.invoice_number = gp_order_items.invoice_number
    WHERE
      gp_order_items.rx_number = $rx_number
  ";

  $sql2 = "
    SELECT
      *,
      gp_rxs_grouped.*,
      gp_orders.invoice_number,
      gp_order_items.invoice_number as dontuse_item_invoice,
      gp_orders.invoice_number as dontuse_order_invoice,
      0 as is_order,
      0 as is_patient,
      1 as is_item
    FROM
      gp_rxs_single
    LEFT JOIN gp_patients ON
      gp_rxs_single.patient_id_cp = gp_patients.patient_id_cp
    LEFT JOIN gp_rxs_grouped ON
      rx_numbers LIKE CONCAT('%,', gp_rxs_single.rx_number, ',%')
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
    LEFT JOIN gp_order_items ON -- choice to show any order_item from this rx_group and not just if this specific rx matches
      rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
    LEFT JOIN gp_orders ON -- ORDER MAY HAVE NOT BEEN ADDED YET
      gp_orders.invoice_number = gp_order_items.invoice_number
    WHERE
      gp_rxs_single.rx_number = $rx_number
  ";

  $res1 = $mysql->run($sql1);
  $res2 = $mysql->run($sql2);

  SirumLog::alert(
    "load_full_item: CANNOT GET FULL_ITEM! MOST LIKELY WILL NOT BE PENDED IN V2",
    [
      'res1'         => $res1,
      'sql1'         => $sql1,
      'res2'         => $res2,
      'sql2'         => $sql2,
      'sql'          => $sql,
      'rx_number'    => $rx_number,
    ]
  );
}
