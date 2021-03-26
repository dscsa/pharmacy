<?php

require_once 'exports/export_gd_orders.php';

use \GoodPill\Logging\GPLog;

function helper_update_payment($order, $reason, $mysql)
{
    GPLog::notice(
        'helper_update_payment start',
        [
          'order_before' => $order,
          'reason' => $reason,
          'invoice_number' => $order[0]['invoice_number']
      ]
    );

    $old_payment_total_default = $order[0]['payment_total_default'];
    $old_payment_fee_default   = $order[0]['payment_fee_default'];
    $old_payment_due_default   = $order[0]['payment_due_default'];

    $order = get_payment_default($order, $reason);

    $is_payment_change = (
        $order[0]['payment_total_default'] != $old_payment_total_default or
    $order[0]['payment_fee_default']   != $old_payment_fee_default or
    $order[0]['payment_due_default']   != $old_payment_due_default
    );

    if ($is_payment_change) {
        GPLog::warning(
            'helper_update_payment is_payment_change:true',
            [
              'order'                     => $order,
              'reason'                    => $reason,
              'old_payment_total_default' => $old_payment_total_default,
              'old_payment_fee_default'   => $old_payment_fee_default,
              'old_payment_due_default'   => $old_payment_due_default,
              'invoice_number' => $order[0]['invoice_number']
            ]
        );
        set_payment_default($order, $mysql);
        export_wc_update_order_payment($order[0]['invoice_number'], $order[0]['payment_fee_default'], $order[0]['payment_due_default']);
        return $order;
    }

    GPLog::notice(
        'helper_update_payment is_payment_change:false',
        [
            'order'                     => $order,
            'reason'                    => $reason,
            'old_payment_total_default' => $old_payment_total_default,
            'old_payment_fee_default'   => $old_payment_fee_default,
            'old_payment_due_default'   => $old_payment_due_default,
            'invoice_number' => $order[0]['invoice_number']
          ]
    );

    return $order;
}

function get_payment_default($order, $reason)
{
    GPLog::debug("get_payment_default", ['order' => $order, 'reason' => $reason]);

    $update = [];

    $update['payment_total_default'] = 0;

    foreach ($order as $i => $item) {
        $update['payment_total_default'] += (@$item['price_dispensed'] ?: 0);
    }

    //Defaults
    $update['payment_fee_default']  = (int) $order[0]['refills_used'] ? $update['payment_total_default'] : PAYMENT_TOTAL_NEW_PATIENT;
    $update['payment_due_default']  = $update['payment_fee_default'];
    $update['payment_date_autopay'] = 'NULL';

    if (@$order[0]['payment_method'] == PAYMENT_METHOD['COUPON']) {
        $update['payment_fee_default'] = $update['payment_total_default'];
        $update['payment_due_default'] = 0;
    } elseif (@$order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY']) {
        $start = date('m/01', strtotime('+ 1 month'));
        $stop  = date('m/07/y', strtotime('+ 1 month'));

        $update['payment_date_autopay'] = "'$start - $stop'";
        $update['payment_due_default'] = 0;
    }

    GPLog::notice(
        "get_payment_default: Order ".$order[0]['invoice_number'],
        [
            'order - before update merged' => $order,
            'update' => $update,
            'reason' => $reason,
            'invoice_number' => $order[0]['invoice_number']
        ]
    );

    foreach ($order as $i => $item) {
        $order[$i] = array_merge($item, $update);
    }

    return $order;
}

function set_payment_default($order, $mysql)
{
    $sql = "UPDATE
              gp_orders
            SET
              payment_total_default = {$order[0]['payment_total_default']},
              payment_fee_default   = {$order[0]['payment_fee_default']},
              payment_due_default   = {$order[0]['payment_due_default']},
              payment_date_autopay  = {$order[0]['payment_date_autopay']}
            WHERE
              invoice_number = {$order[0]['invoice_number']}";

    $mysql->run($sql);
}

function set_payment_actual($invoice_number, $payment, $mysql)
{
    $sql = "
    UPDATE
      gp_orders
    SET
      payment_total_actual = ".($payment['total'] ?: 'NULL').",
      payment_fee_actual   = ".($payment['fee'] ?: 'NULL').",
      payment_due_actual   = ".($payment['due'] ?: 'NULL')."
    WHERE
      invoice_number = $invoice_number
  ";

    $mysql->run($sql);
}
