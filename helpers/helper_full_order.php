<?php
//order created -> add any additional rxs to order -> import order items -> sync all drugs in order

function get_full_order($order, $mysql) {

  //gp_orders.invoice_number and other fields at end because otherwise potentially null gp_order_items.invoice_number will override gp_orders.invoice_number
  $sql = "
    SELECT
      *,
      gp_orders.invoice_number,
      gp_rxs_grouped.* -- Need to put this first based on how we are joining, but make sure these grouped fields overwrite their single equivalents
    FROM
      gp_orders
    JOIN gp_patients ON
      gp_patients.patient_id_cp = gp_orders.patient_id_cp
    LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
      gp_rxs_grouped.patient_id_cp = gp_orders.patient_id_cp
    LEFT JOIN gp_order_items ON
      gp_order_items.invoice_number = gp_orders.invoice_number AND rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%') -- In case the rx is added in a different orders
    LEFT JOIN gp_rxs_single ON -- Needed to know qty_left for sync-to-date
      gp_order_items.rx_number = gp_rxs_single.rx_number
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic -- this is for the helper_days_dispensed msgs for unordered drugs
    WHERE
      gp_orders.invoice_number = $order[invoice_number]
  ";

  $order = $mysql->run($sql)[0];

  if ( ! $order OR ! $order[0]['invoice_number'])
    return log_error('ERROR! get_full_order: no invoice number', get_defined_vars());

  $order = add_wc_fields_to_order($order);
  $order = add_gd_fields_to_order($order);

  return $order;
}

function add_wc_fields_to_order($order) {

  $order_meta = wc_select_order($order[0]['invoice_number']);

  $wc_fields = ['order_status_wc' => $order_meta[0]['post_status']];

  foreach($order_meta as $meta)
    $wc_fields[$meta['meta_key']] = $meta['meta_value'];

  foreach($order as $i => $item)
    $order[$i] = $item + $wc_fields;

  log_notice('add_wc_fields_to_order', get_defined_vars());

  return $order;
}

//Simplify GDoc Invoice Logic by combining _actual
function add_gd_fields_to_order($order) {

  //Consolidate default and actual suffixes to avoid conditional overload in the invoice template and redundant code within communications
  foreach($order as $i => $item) {
    $order[$i]['drug'] = $item['drug_name'] ?: $item['drug_generic'];
    $order[$i]['days_dispensed'] = $item['days_dispensed_actual'] ?: $item['days_dispensed_default'];

    if ( ! $item['item_date_added']) { //if not syncing to order lets provide a reason why we are not filling
      $message = get_days_default($item)[1];
      $order[$i]['item_message_key']  = array_search($message, RX_MESSAGE);
      $order[$i]['item_message_text'] = message_text($message, $item);
    }

    $deduct_refill = $order[$i]['days_dispensed'] ? 1 : 0; //We want invoice to show refills after they are dispensed assuming we dispense items currently in order

    $order[$i]['qty_dispensed'] = (float) ($item['qty_dispensed_actual'] ?: $item['qty_dispensed_default']); //cast to float to get rid of .000 decimal
    $order[$i]['refills_total'] = (float) ($item['refills_total_actual'] ?: $item['refills_total_default'] - $deduct_refill);
    $order[$i]['price_dispensed'] = (float) ($item['price_dispensed_actual'] ?: ($item['price_dispensed_default'] ?: 0));
  }

  usort($order, 'sort_order_by_day');

  //log_info('get_full_order', get_defined_vars());

  return $order;
}

function sort_order_by_day($a, $b) {
  if ($b['days_dispensed'] > 0 AND $a['days_dispensed'] == 0) return 1;
  if ($a['days_dispensed'] > 0 AND $b['days_dispensed'] == 0) return -1;
  return strcmp($a['item_message_text'].$a['drug'], $b['item_message_text'].$b['drug']);
}
