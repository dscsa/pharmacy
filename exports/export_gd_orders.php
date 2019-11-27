<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_update_invoice($order) {

  email("WebForm export_gd_update_invoice 1", $order);

  log_info("
  export_gd_update_invoice ");//.print_r($item, true);

  if ( ! count($order)) return;

  //Consolidate default and actual suffixes to avoid conditional overload in the invoice template
  foreach($order as $i => $item) {
    $order[$i]['item_message_text'] = $item['invoice_number'] ? ($item['item_message_text'] ?: ''): get_days_dispensed($item)[1]; //Get rid of NULL. //if not syncing to order lets provide a reason why we are not filling
    $order[$i]['days_dispensed'] = $item['days_dispensed_actual'] ?: $item['days_dispensed_default'];
    $order[$i]['qty_dispensed'] = (float) $item['qty_dispensed_actual'] ?: (float) $item['qty_dispensed_default']; //cast to float to get rid of .000 decimal
    $order[$i]['refills_total'] = $item['refills_total_actual'] ?: $item['refills_total_default'];
    $order[$i]['price_dispensed'] = (float) $item['price_dispensed_actual'] ?: (float) $item['price_dispensed_default'];
  }

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Invoice Template v1',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME,
    'order'    => $order
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  //$response = json_decode( $result, true);
  email("WebForm export_gd_update_invoice 2", $args, $result);

  log_info($result);
}

function export_gd_delete_invoice($order) {

  email("WebForm export_gd_delete_invoice 1", $order);

  log_info("
  export_gd_delete_invoice ");//.print_r($item, true);

  $args = [
    'method'   => 'removeFiles',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  //$response = json_decode( $result, true);
  email("WebForm export_gd_delete_invoice 2", $args, $result);

  log_info($result);
}
