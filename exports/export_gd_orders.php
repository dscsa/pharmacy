<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_update_invoice($order) {

  //mail('adam@sirum.org', "WebForm export_gd_update_invoice 1", json_encode($order));

  log_info("
  export_gd_update_invoice ");//.print_r($item, true);

  if ( ! count($order)) return;

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Invoice Template v1',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME,
    'order'    => $order
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  //$response = json_decode( $result );
  //mail('adam@sirum.org', "WebForm export_gd_update_invoice 2", json_encode([$args, $result]));

  log_info($result);
}
