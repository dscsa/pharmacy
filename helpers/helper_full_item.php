<?php

use Sirum\Logging\{
    SirumLog,
    AuditLog,
    CliLog
};

function load_full_item($partial, $mysql, $overwrite_rx_messages = false) {

    if ( ! $partial['rx_number']) {
        log_error('ERROR load_full_item: missing rx_number', get_defined_vars());
        return [];
    }

    $item = get_full_item($mysql, $partial['rx_number'], @$partial['invoice_number']);

    if ( ! $item) {
        log_error('ERROR load_full_item: get_full_item missing ', get_defined_vars());
        return [];
    }

    //use [item] to mock an order, this won't be quite as good because is_refill and sync_to_order
    //need the whole order in order to determine what to do but it will be directionally correct
    if ( ! @$partial['invoice_number']) {
        log_notice('load_full_item: rx only, no invoice_number ', get_defined_vars());
        return add_full_fields([$item], $mysql, $overwrite_rx_messages)[0];
    }

    $order = get_full_order($mysql, $partial['invoice_number']);

    //order will be null for the order-items-deleted loop
    //use [item] to mock an order, this won't be quite as good because is_refill and sync_to_order
    //need the whole order in order to determine what to do but it will be directionally correct
    if ( ! $order) {
      log_notice("load_full_item: rx only, order {$partial['invoice_number']} deleted ", get_defined_vars());
      return add_full_fields([$item], $mysql, $overwrite_rx_messages)[0];
    }

    $full_order = add_full_fields($order, $mysql, $overwrite_rx_messages ? $item['rx_number'] : false);

    foreach ($full_order as $full_item) {

        //$item might not have rx_number if not in order so need to use $partial
        if ($full_item['rx_number'] != $partial['rx_number']) {
            continue;
        }

        log_warning("load_full_item: matching order and item found", [
          'item' => $item,
          'full_item' => $full_item,
          'full_item not item' => array_diff_assoc($full_item, $item),
          'item not full_item' => array_diff_assoc($item, $full_item)
        ]);

        return $full_item;
    }

    SirumLog::alert("load_full_item: order found but no matching item!", ['partial' => $partial, 'item' => $item, 'order' => $order]);
    return [];
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
     SirumLog::warning(($item['rx_gsn'] ? 'get_full_item: Add GSN to V2!' : 'get_full_item: Missing GSN!')." Invoice Number:$item[invoice_number] Drug:$item[drug_name] Rx:$item[rx_number] GSN:$item[rx_gsn] GSNS:$item[drug_gsns]", ['item' => $item, 'partial' => $partial, 'sql' => $sql]);
  }

  SirumLog::notice(
      'get_full_item: success',
      [
          'invoice_number' => $invoice_number,
          'sql'   => $sql,
          'query' => $query
      ]
  );

  return $item;
}

/* this will most commonly called with a set of rx_grouped.rx_numbers */
function get_current_items($mysql, $conditions = []) {
  $where = "";

  foreach ($conditions as $key => $val) {
    $where .= "$key = $val AND\n";
  }

  $sql = "
    SELECT *
    FROM gp_order_items
    JOIN gp_rxs_grouped ON
      gp_rxs_grouped.rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
    WHERE
      $where
      rx_dispensed_id IS NULL
    ORDER BY
      invoice_number ASC
  ";

   $res = $mysql->run($sql)[0];

   return $res;
}
