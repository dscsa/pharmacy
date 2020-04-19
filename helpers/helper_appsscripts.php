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
    'folder'       => INVOICE_PUBLISHED_FOLDER_NAME
  ];

  $invoices = json_decode(gdoc_post(GD_HELPER_URL, $args), true);

  if ( ! is_array($invoices))
    return log_error('ERROR watch_invoices', compact($invoices, $args), null);

  $mysql = new Mysql_Wc();

  foreach ($invoices as $invoice) {

    preg_match_all('/(Total:? +|Due:? +)\$(\d+)/', $invoice['part0'], $totals);

    //Table columns seem to be divided by table breaks
    preg_match_all('/\\n\$(\d+)/', $invoice['part0'], $items);

    //Differentiate from the four digit year
    preg_match_all('/\d{5,}/', $invoice['name'], $invoice_number);

    if ( ! isset($totals[2][0]) OR ! isset($totals[2][1])) {
      log_error('watch_invoices: incorrect totals', $invoice['part0']);
      continue;
    }

    if ( ! isset($invoice_number[0][0])) {
      log_error('watch_invoices: incorrect invoice number', $invoice_number);
      continue;
    }

    $payment = [
      'total' => array_sum($items[1]),
      'fee'   => $totals[2][0],
      'due'   => $totals[2][1]
    ];

    //TODO LOG NOTICE IF COUNT_FILLED != len($items[1])
    log_notice('watch_invoices', get_defined_vars());

    set_payment_actual($invoice_number[0][0], $payment, $mysql);
    export_wc_update_order_payment($invoice_number[0][0], $payment['fee']);
  }

  return $invoices;
}
