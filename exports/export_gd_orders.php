<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_update_invoice($order) {

  email("WebForm export_gd_update_invoice 1", $order);

  log_info("
  export_gd_update_invoice ");//.print_r($item, true);

  if ( ! count($order)) return;

  export_gd_delete_invoice($order); //Avoid having multiple versions of same invoice

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

//Cannot delete (with this account) once published
function export_gd_publish_invoices($order) {

   if ( ! $order[0]['tracking_number']) return; //only publish if tracking number since we can't delete extra after this point

    $args = [
      'method'   => 'publishFile',
      'file'     => 'Invoice #'.$order[0]['invoice_number'],
      'folder'   => INVOICE_FOLDER_NAME,
    ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    //$response = json_decode( $result, true);
    email("WebForm export_gd_publish_invoices", $args, $result);
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
