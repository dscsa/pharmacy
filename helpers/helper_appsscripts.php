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

function watch_invoices() {

  $args = [
    'method'       => 'watchFiles',
    'folder'       => INVOICE_FOLDER_NAME
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  email('watch_invoices', $args, $result);

  /*

  //Parse Invoice Text and Look For Changes
    if ( ! match) continue

    var text   = match.getElement()
    var value  = text.replace(RegExp('('+content.needle+') *'), '')
    var digits = value.replace(/\D/g, '')
    var match  = text.replace(RegExp(' *'+value.replace('$', '\\$')), '')

    res.values.push({text:text, match:match, value:value, digits:digits})

    findText('('+content.needle+') *[$\w]+')


    $order  = set_payment($order, get_payment($order), $mysql);
  */
  return $result;


}
