<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_transfer_fax($item, $source) {

  log_notice("WebForm export_gd_transfer_fax CALLED $item[invoice_number] $source", get_defined_vars());

  if (
    $item['rx_message_key']  != 'NO ACTION WILL TRANSFER' AND
    $item['rx_message_key']  != 'NO ACTION WILL TRANSFER CHECK BACK' AND
    ($item['rx_message_key'] != 'NO ACTION MISSING GSN' OR ! $item['max_gsn'])
  )
    return;

  if ($item['rx_transfer']) {
    return log_error("WebForm export_gd_transfer_fax NOT SENT, ALREADY TRANSFERRED $item[invoice_number] $source", get_defined_vars());
  }

  $to = $item['pharmacy_fax'] ?: '8889875187';

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Transfer Template v1',
    'file'     => "Transfer Out #$item[invoice_number] Rx:$item[best_rx_number] Fax:$to",
    'folder'   => TRANSFER_OUT_FOLDER_NAME,
    'order'    => [$item]
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  log_error("WebForm export_gd_transfer_fax SENT $item[invoice_number] $source", get_defined_vars());
}
