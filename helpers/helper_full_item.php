<?php

use Sirum\Logging\SirumLog;

function load_full_item($partial, $mysql, $overwrite_rx_messages = false) {

  if ( ! $partial['rx_number']) {
    log_error('ERROR get_full_item: missing rx_number', get_defined_vars());
    return [];
  }

  $item = get_full_item($mysql, $partial['rx_number'], @$partial['invoice_number']);

  if ( ! $item) {
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

  if ( ! @$query[0]) {
    return;
  }

  $item = $query[0];

  if ( ! $item['drug_generic']) {
    log_warning(($item['rx_gsn'] ? 'get_full_item: Add GSN to V2!' : 'get_full_item: Missing GSN!')." Invoice Number:$item[invoice_number] Drug:$item[drug_name] Rx:$item[rx_number] GSN:$item[rx_gsn] GSNS:$item[drug_gsns]", ['item' => $item, 'partial' => $partial, 'sql' => $sql]);
  }

  log_notice('get_full_item: success', ['sql' => $sql, 'query' => $query]);

  return $item;
}
