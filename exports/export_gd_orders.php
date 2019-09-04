<?php

function export_gd_update_invoice($order) {

  echo "
  export_gd_update_invoice ";//.print_r($order_item, true);

  $opts = [
    'http' => [
      'method'  => 'POST',
      'content' => json_encode( $order ),
      'header'  => "Content-Type: application/json\r\n" .
                   "Accept: application/json\r\n"
    ]
  ];

  $context  = stream_context_create( $opts );
  $result = file_get_contents( GD_URL.'?'.GD_KEY, false, $context );
  //$response = json_decode( $result );

  echo $result;
}
