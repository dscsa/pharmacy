<?php

require_once 'exports/export_gd_orders.php';


function helper_update_payment($order, $reason, $mysql) {
  $update = get_payment($order);
  $order  = set_payment_default($order, $update, $reason, $mysql);

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

function set_payment_default($order, $update, $reason, $mysql) {

  if (
    isset($order[0]['payment_total_default']) AND
    isset($order[0]['payment_fee_default']) AND
    isset($order[0]['payment_due_default']) AND
    $order[0]['payment_total_default'] == $update['payment_total_default'] AND
    $order[0]['payment_fee_default'] == $update['payment_fee_default'] AND
    $order[0]['payment_due_default'] == $update['payment_due_default']
  ) {

    log_error('set_payment_default: but no changes', [$order, $update, $reason]);
    
  }

  if ($reason == "update_orders_wc: deleted - 0 items")
    log_error('set_payment_default update_orders_wc: deleted - 0 items HAD CHANGES', [$order, $update, $reason]);

  //Update order before we make the invoice
  foreach($order as $i => $item)
    $order[$i] = $update + $item;

  $invoice_doc_id = export_gd_update_invoice($order);

  //Need to make a second loop to now update the invoice number
  foreach($order as $i => $item)
    $order[$i]['invoice_doc_id'] = $invoice_doc_id;

  $sql = "
    UPDATE
      gp_orders
    SET
      payment_total_default = $update[payment_total_default],
      payment_fee_default   = $update[payment_fee_default],
      payment_due_default   = $update[payment_due_default],
      payment_date_autopay  = $update[payment_date_autopay],
      invoice_doc_id        = ".($invoice_doc_id ? "'$invoice_doc_id'" : 'NULL')."
    WHERE
      invoice_number = {$order[0]['invoice_number']}
  ";

  $mysql->run($sql);

  return $order;
}

function set_payment_actual($invoice_number, $payment, $mysql) {

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
