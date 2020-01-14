<?php

require_once 'exports/export_gd_orders.php';


function helper_update_payment($order, $mysql) {
  $update = get_payment($order);
  $order  = set_payment_default($order, $update, $mysql);

  export_gd_update_invoice($order, $mysql);
  return $order;
}

function get_payment($order) {

  $update = [];

  $update['payment_total_default'] = 0;

  foreach($order as $i => $item)
    $update['payment_total_default'] += $item['price_dispensed'];

  //Defaults
  $update['payment_fee_default'] = $order[0]['refills_used'] ? $update['payment_total_default'] : PAYMENT_TOTAL_NEW_PATIENT;
  $update['payment_due_default'] = $update['payment_fee_default'];
  $update['payment_date_autopay'] = 'NULL';

  if ($order[0]['payment_method'] == PAYMENT_METHOD['COUPON']) {
    $update['payment_fee_default'] = $update['payment_total_default'];
    $update['payment_due_default'] = 0;
  }
  else if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY']) {
    $start = date('m/01', strtotime('+ 1 month'));
    $stop  = date('m/07/y', strtotime('+ 1 month'));

    $update['payment_date_autopay'] = "'$start - $stop'";
    $update['payment_due_default'] = 0;
  }

  return $update;
}

function set_payment_default($order, $update, $mysql) {

  $sql = "
    UPDATE
      gp_orders
    SET
      payment_total_default = $update[payment_total_default],
      payment_fee_default   = $update[payment_fee_default],
      payment_due_default   = $update[payment_due_default],
      payment_date_autopay  = $update[payment_date_autopay]
    WHERE
      invoice_number = {$order[0]['invoice_number']}
  ";

  $mysql->run($sql);

  foreach($order as $i => $item)
    $order[$i] = $update + $item;

  return $order;
}

function set_payment_actual($invoice_number, $payment, $mysql) {

  $sql = "
    UPDATE
      gp_orders
    SET
      payment_total_actual = $payment[total],
      payment_fee_actual   = $payment[fee],
      payment_due_actual   = $payment[due]
    WHERE
      invoice_number = $invoice_number
  ";

  $mysql->run($sql);
}
