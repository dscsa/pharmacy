<?php

//TODO Since we share a databse should we just do direct DB calls?
function wc_fetch($url, $method = 'GET', $content = []) {

  $opts = [
      /*
      "socket"  => [
        'bindto' => "0:$port",
      ],
      */
      "http" => [
        'method'  => $method,
        'content' => json_encode($content),
        'header'  => "Content-Type: application/json\r\n".
                     "Accept: application/json\r\n".
                     "Authorization: Basic ".base64_encode(WC_USER.':'.WC_PWD)
      ]
  ];

  $url = WC_IP."/wp-json/wc/v2/$url";

  log_notice("wc_fetch", get_defined_vars());

  //$context = stream_context_create($opts);

  //$response = file_get_contents($url, false, $context);

  //if (res.code != "woocommerce_rest_shop_order_invalid_id") return res

  //var success = parsed.number || parsed.code == "refill_order_already_exists" || parsed.code == "woocommerce_rest_shop_order_invalid_id"

  //if ( ! success)
  //  debugEmail('saveWebformOrder success?', 'action: '+action, 'endpoint: '+endpoint, 'http code: '+response.getResponseCode(), 'headers', response.getHeaders(), 'request', woocommerceOrder, 'response', parsed)


  //return json_decode($response, true);
}
