<?php

function v2_fetch($url, $method = 'GET', $content = []) {

  $context = stream_context_create([
      "http" => [
          'method'  => $method,
          'content' => json_encode($content),
          'header'  => "Content-Type: application/json\r\n".
                       "Accept: application/json\r\n".
                       "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD)."\r\n"
      ]
  ]);

  $response = file_get_contents(V2_IP.$url, false, $context);
  email('v2_fetch', V2_IP.$url, $context, $response);
  return json_decode($response, true);
}
