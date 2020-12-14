<?php

function gdoc_post($url, $content)
{
    $content = json_encode(utf8ize($content), JSON_UNESCAPED_UNICODE);

    $opts = [
        'http' => [
          'method'  => 'POST',
          'content' => $content,
          'header'  => "Content-Type: application/json\r\n".
                       "Accept: application/json\r\n".
                       'Content-Length: '.strlen($content)."\r\n" //Apps Scripts seems to sometimes to require this e.g Invoice for 33701 or returns an HTTP 411 error
        ]
    ];

    $context = stream_context_create($opts);
    return file_get_contents($url.'?GD_KEY='.GD_KEY, false, $context);
}

function watch_invoices()
{
    $args = [
        'method'       => 'watchFiles',
        'folder'       => INVOICE_PUBLISHED_FOLDER_NAME
    ];


    $response = json_decode(gdoc_post(GD_HELPER_URL, $args), true);

    if (! is_array($response)
            or ! is_array($response['parent'])
            or ! is_array($response['printed'])
            or ! is_array($response['faxed'])) {
        return log_error('ERROR watch_invoices', [$response, $args]);
    }

    $invoices = array_merge($response['parent'], $response['printed'], $response['faxed']);

    printf(
        "Total Invoices %d, Parent %d, Printed %d, Faxed %d\n",
        count($invoices),
        count($response['parent']),
        count($response['printed']),
        count($response['faxed'])
    );

    $mysql = new Mysql_Wc();

    foreach ($invoices as $invoice) {

        preg_match_all('/(Total:? +|Due:? +)\$(\d+)/', $invoice['part0'], $totals);

        //Table columns seem to be divided by table breaks
        preg_match_all('/\\n\$(\d+)/', $invoice['part0'], $items);

        //Differentiate from the four digit year
        preg_match_all('/\d{5,}/', $invoice['name'], $invoice_number);

        if (! isset($totals[2][0]) or ! isset($totals[2][1])) {
            log_error('watch_invoices: incorrect totals', $invoice['part0']);
            continue;
        }

        if (! isset($invoice_number[0][0])) {
            log_error('watch_invoices: incorrect invoice number', $invoice_number);
            continue;
        }

        $invoice_number = $invoice_number[0][0];

        $payment = [
            'count_filled' => count($items[1]),
            'total' => array_sum($items[1]),
            'fee'   => $totals[2][0],
            'due'   => $totals[2][1]
        ];

        $sql = "SELECT * FROM gp_orders WHERE invoice_number = $invoice_number";

        $order = $mysql->run($sql)[0][0];

        $log = "Filled:$order[count_filled] -> $payment[count_filled],
                Total:$order[payment_total_default] ($order[payment_total_actual]) -> $payment[total],
                Fee:$order[payment_fee_default] ($order[payment_fee_actual]) -> $payment[fee],
                Due:$order[payment_due_default] ($order[payment_due_actual]) -> $payment[due]\n\n";


        if ($order['count_filled'] == $payment['count_filled'] &&
                ($order['payment_total_actual'] ?: $order['payment_total_default']) == $payment['total'] &&
                ($order['payment_fee_actual'] ?: $order['payment_fee_default']) == $payment['fee'] &&
                ($order['payment_due_actual'] ?: $order['payment_due_default']) == $payment['due']
        ) {

            log_notice("watch_invoice $invoice_number", $log);
            continue;
        } //Most likely invoice was correct and just moved

        log_error("watch_invoice $invoice_number", $log);

        set_payment_actual($invoice_number, $payment, $mysql);
        export_wc_update_order_payment($invoice_number, $payment['fee'], $payment['due']);
    }

    return $invoices;
}
