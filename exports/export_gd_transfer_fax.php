<?php

require_once 'helpers/helper_appsscripts.php';

function export_gd_transfer_fax($item) {

  log_notice("WebForm export_gd_transfer_fax CALLED", get_defined_vars());

  if ($item['rx_message_key'] != 'NO ACTION WILL TRANSFER' AND $item['rx_message_key'] != 'NO ACTION WILL TRANSFER CHECK BACK')
    return;

  $to = $item['pharmacy_fax'] ?: '888-987-5187';

  $args = [
    'method'   => 'mergeDoc',
    'template' => 'Transfer Out Template v1',
    'file'     => 'Transfer Out #'.$item['rx_number']." $to",
    'folder'   => 'Test Transfers', //Transfer Outs
    'order'    => [$item]
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);

  log_error("WebForm export_gd_transfer_fax SENT", get_defined_vars());
}
