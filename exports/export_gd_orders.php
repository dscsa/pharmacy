<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_update_invoice($order) {

  email("WebForm export_gd_update_invoice 1", $order);

  log_info("
  export_gd_update_invoice ");//.print_r($item, true);

  if ( ! count($order)) return;

  //Consolidate default and actual suffixes to avoid conditional overload in the invoice template
  foreach($order as $item) {
    $order['days_dispensed'] = $order['days_dispensed_actual'] ?: $order['days_dispensed_default'];
    $order['qty_dispensed'] = $order['qty_dispensed_actual'] ?: $order['qty_dispensed_default'];
    $order['refills_total'] = $order['refills_total_actual'] ?: $order['refills_total_default'];
    $order['price_dispensed'] = $order['price_dispensed_actual'] ?: $order['price_dispensed_default'];
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
