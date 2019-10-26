<?php

function export_gd_update_invoice($order) {

  log_info("
  export_gd_update_invoice ");//.print_r($order_item, true);

  if ( ! count($order)) return;

  $args = [
    'method'   => 'mergeDocs',
    'template' => 'Invoice Template v1',
    'file'     => 'Invoice #'.$order['order_id'],
    'folder'   => 'Published',
    'order'    => $order
  ];

  $result = gdoc_post(GD_INVOICE_URL, $args);

  //$response = json_decode( $result );
  mail('adam@sirum.org', "WebForm export_gd_update_invoice", json_encode([$args, $result]));

  log_info($result);
}
