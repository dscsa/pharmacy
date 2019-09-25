<?php

function export_gd_update_invoice($order) {

  log("
  export_gd_update_invoice ");//.print_r($order_item, true);

  if ( ! count($order)) return;

  $opts = [
    'http' => [
      'method'  => 'POST',
      'content' => json_encode( $order ),
      'header'  => "Content-Type: application/json\r\n" .
                   "Accept: application/json\r\n"
    ]
  ];

  $context = stream_context_create( $opts );
  $result  = file_get_contents( GD_URL.'?GD_KEY='.GD_KEY, false, $context );
  //$response = json_decode( $result );

  log($result);
}
