<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_update_invoice($order, $reason, $mysql) {

  if ( ! count($order)) {
    log_error("export_gd_update_invoice: got malformed order", [$order, $reason]);
    return $order;
  }

  $start = microtime(true);

  export_gd_delete_invoice($order); //Avoid having multiple versions of same invoice

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Invoice Template v1',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_PENDING_FOLDER_NAME,
    'order'    => $order
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  $invoice_doc_id = json_decode($result, true);

  if ( ! $invoice_doc_id) {
    log_error("export_gd_update_invoice: invoice error", ['file' => $args['file'], 'result' => $result]);
    return $order;
  }

  $time = ceil(microtime(true) - $start);

  if ($order[0]['invoice_doc_id'])
    log_notice("export_gd_update_invoice: updated invoice for Order #".$order[0]['invoice_number'].' '.$order[0]['order_stage_cp']." $time seconds. docs.google.com/document/d/".$order[0]['invoice_doc_id']." >>>  docs.google.com/document/d/$invoice_doc_id", [$order, $reason]);
  else
    log_notice("export_gd_update_invoice: created invoice for Order #".$order[0]['invoice_number'].' '.$order[0]['order_stage_cp']." $time seconds. docs.google.com/document/d/$invoice_doc_id", [$order, $reason]);

  //Need to make a second loop to now update the invoice number
  foreach($order as $i => $item)
    $order[$i]['invoice_doc_id'] = $invoice_doc_id;

  $sql = "
    UPDATE
      gp_orders
    SET
      invoice_doc_id = ".($invoice_doc_id ? "'$invoice_doc_id'" : 'NULL')." -- Unique Index forces us to use NULL rather than ''
    WHERE
      invoice_number = {$order[0]['invoice_number']}
  ";

  $mysql->run($sql);

  return $order;
}

//Cannot delete (with this account) once published
function export_gd_publish_invoice($order) {

  if ( ! $order[0]['order_date_shipped']) return; //only publish if tracking number since we can't delete extra after this point

  $start = microtime(true);

  $args = [
    'method'   => 'publishFile',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_PENDING_FOLDER_NAME,
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  $args = [
    'method'   => 'publishFile',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => 'Old',
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  $args = [
    'method'   => 'moveFile',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'fromFolder' => INVOICE_PENDING_FOLDER_NAME,
    'toFolder'   => INVOICE_PUBLISHED_FOLDER_NAME,
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  /*Temp*/
  $args = [
    'method'   => 'moveFile',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'fromFolder' => 'Old',
    'toFolder'   => INVOICE_PUBLISHED_FOLDER_NAME,
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);
  /* End Temp */

  $time = ceil(microtime(true) - $start);

  log_notice("export_gd_update_invoice $time seconds: ".$order[0]['invoice_number']);
}

function export_gd_delete_invoice($order) {

  $args = [
    'method'   => 'removeFiles',
    'file'     => 'Invoice #'.$order[0]['invoice_number'],
    'folder'   => INVOICE_PENDING_FOLDER_NAME
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_info("export_gd_delete_invoice", get_defined_vars());
}
