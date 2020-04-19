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

  $to = $item['pharmacy_fax'] ?: '888-987-5187';

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Transfer Out Fax v1',
    'file'     => "Transfer Out #$item[best_rx_number] Fax:$to",
    'folder'   => 'Test Transfers', //Transfer Outs
    'order'    => [$item]
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  log_error("WebForm export_gd_transfer_fax SENT $item[invoice_number] $source", get_defined_vars());
}
