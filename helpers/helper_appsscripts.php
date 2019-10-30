<?php

function gdoc_post($url, $content) {
  $opts = [
    'http' => [
      'method'  => 'POST',
      'content' => json_encode($content),
      'header'  => "Content-Type: application/json\r\n" .
                   "Accept: application/json\r\n"
    ]
  ];

  $context = stream_context_create($opts);
  return file_get_contents($url.'?GD_KEY='.GD_KEY, false, $context);
}
