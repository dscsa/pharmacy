<?php

use Sirum\Logging\SirumLog;

/**
 * Get the details about a specific file to use for test.
 * @param  string $fileId The filedId of the google doc
 * @return object         The response from google converted to an object
 */
function gdoc_details($fileId)
{
    $opts = [
        'http' => [
          'method'  => 'GET',
          'header'  => "Accept: application/json\r\n"
        ]
    ];

    $context = stream_context_create($opts);
    $url     = sprintf(
        "%s?GD_KEY=%s&fileId=%s",
        GD_FILE_URL,
        GD_KEY,
        $fileId
    );

    $results = json_decode(file_get_contents($url, false, $context));

    return $results;
}

/**
 * Make a post to the gdoc url.
 *
 * @param  string $url     The url to request
 * @param  array  $content The data to send to the request
 * @return string          The response from Google
 */
function gdoc_post($url, $content)
{
    global $global_exec_details;

    $start = microtime(true);

    $json  = json_encode(utf8ize($content), JSON_UNESCAPED_UNICODE);

    $opts = [
        'http' => [
          'method'  => 'POST',
          'content' => $json,
          'timeout' => 120,
          'header'  => "Content-Type: application/json\r\n".
                       "Accept: application/json\r\n".
                       // Apps Scripts seems to sometimes to require this e.g
                       // Invoice for 33701 or returns an HTTP 411 error
                       'Content-Length: '.strlen($json)."\r\n"
        ]
    ];

    $context = stream_context_create($opts);


    for ($try = 1; $try <=2; $try++) {
        $results = @file_get_contents($url.'?GD_KEY='.GD_KEY, false, $context);

        // We have some results so let's leave
        if ($results !== false) {
            SirumLog::debug(
                'Google Doc Request Success:',
                [
                    'data' => json_decode($json),
                    'url'  => $url,
                    'results' => $results,
                    'try' => $try
                ]
            );
            break;
        }

        echo "failed $try\n";

        SirumLog::error(
            'Google Doc Request Failed:',
            [
                'data'    => json_decode($json),
                'url'     => $url,
                'try'     => $try,
                'results' => $results
            ]
        );

        // Exponetial sleep
        usleep((300 - ($try * 50)) * pow(3, $try));
    }

    // Differentiate between removeCalendarEvents
    $ids = @$content['ids'][0];

    // Lots of squelching so we don't need so many ifs
    $key_fields = [
        @$content['method'],
        @$content['file'],
        @$content['word_search'],
        @$content['title'],
        @$content['ids'][0]
    ];

    $key = implode(' ', $key_fields);

    $global_exec_details['timers_gd'][$key] = ceil(microtime(true) - $start);

    return $results;
}

/**
 * Call the google app to look for invoices that have changed.  If we find any,
 * we need to update the patient portal with the new details
 *
 * @return void
 */
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
        "Total Invoices %d, Parent %d, Printed %d, Faxed %d ",
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

        $sql = "SELECT *
                    FROM gp_orders
                    WHERE invoice_number = {$invoice_number}";

        $order = $mysql->run($sql)[0][0];

        $log = sprintf(
            "Filled: %s -> %s,
             Total: %s (%s) -> %s,
             Fee:%s (%s) -> %s,
             Due:%s (%s) -> %s\n\n",
            $order['count_filled'],
            $payment['count_filled'],
            $order['payment_total_default'],
            $order['payment_total_actual'],
            $payment['total'],
            $order['payment_fee_default'],
            $order['payment_fee_actual'],
            $payment['fee'],
            $order['payment_due_default'],
            $order['payment_due_actual'],
            $payment['due']
        );

        if (
            $order['count_filled'] == $payment['count_filled']
            && ($order['payment_total_actual'] ?: $order['payment_total_default']) == $payment['total']
            && ($order['payment_fee_actual'] ?: $order['payment_fee_default']) == $payment['fee']
            && ($order['payment_due_actual'] ?: $order['payment_due_default']) == $payment['due']
        ) {
            //Most likely invoice was correct and just moved
            log_notice("watch_invoice $invoice_number", $log);
            continue;
        }

        log_error("watch_invoice $invoice_number", $log);

        set_payment_actual($invoice_number, $payment, $mysql);
        export_wc_update_order_payment($invoice_number, $payment['fee'], $payment['due']);
    }

    return $invoices;
}
