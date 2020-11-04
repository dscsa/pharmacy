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

  // Skip POST/PUT/DELETE
  if (ENVIRONMENT != "PRODUCTION"
        && strtolower($method) != 'get') {
    echo "Skipping writes to v2 in development\n";
    return false;
  }

  $context  = stream_context_create($opts);
  $response = file_get_contents(V2_IP.$url, false, $context);

  return json_decode($response, true);
}
