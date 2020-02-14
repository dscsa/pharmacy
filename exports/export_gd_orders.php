<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_update_invoice($order) {

  if ( ! count($order)) return;

  $start = microtime(true);

  export_gd_delete_invoice($order); //Avoid having multiple versions of same invoice

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Invoice Template v1',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME,
    'order'    => $order
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  $invoice_doc_id = json_decode($result, true);

  $time = ceil(microtime(true) - $start);

  if ($order[0]['invoice_doc_id'])
    log_error("export_gd_update_invoice: Updated Order #".$order[0]['invoice_number']." $time seconds. docs.google.com/document/d/".$order[0]['invoice_doc_id']." >>>  docs.google.com/document/d/$invoice_doc_id");
  else
    log_error("export_gd_update_invoice: Created Order #".$order[0]['invoice_number']." $time seconds. docs.google.com/document/d/$invoice_doc_id");

  log_info("export_gd_update_invoice", ['file' => $args['file'], 'result' => $result]);

  return $invoice_doc_id;
}

//Cannot delete (with this account) once published
function export_gd_publish_invoice($order) {

  if ( ! $order[0]['order_date_shipped']) return; //only publish if tracking number since we can't delete extra after this point

  $start = microtime(true);

  $args = [
    'method'   => 'publishFile',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME,
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  $time = ceil(microtime(true) - $start);

  log_error("export_gd_update_invoice $time seconds");
  log_info("export_gd_publish_invoice", get_defined_vars());
}

function export_gd_delete_invoice($order) {

  $args = [
    'method'   => 'removeFiles',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_FOLDER_NAME
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_info("export_gd_delete_invoice", get_defined_vars());
}
