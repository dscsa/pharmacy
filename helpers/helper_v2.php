<?php

function v2_fetch($url, $method = 'GET', $content = []) {

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
                     "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD)
      ]
  ];

  $context = stream_context_create($opts);

  $response = file_get_contents(V2_IP.$url, false, $context);
  //email('v2_fetch', V2_IP.$url, $opts, $response, $http_response_header);
  return json_decode($response, true);
}
