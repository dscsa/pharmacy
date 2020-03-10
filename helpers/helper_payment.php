<?php

require_once 'exports/export_gd_orders.php';


function helper_update_payment($order, $reason, $mysql) {

  log_notice('helper_update_payment', ['order_before' => $order, $reason]);

  $order = get_payment_default($order, $reason);
  set_payment_default($order, $mysql);

  //We include this call in the helper because it MUST be called after get_payment_default or the totals will be wrong
  //If called manually from main thread it is likely that this ordering would not be honored and result in issues
  $order = export_gd_update_invoice($order, $reason, $mysql);

  return $order;
}

function get_payment_default($order, $reason) {

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

  if (
    isset($order[0]['payment_total_default']) AND
    isset($order[0]['payment_fee_default']) AND
    isset($order[0]['payment_due_default']) AND
    $order[0]['payment_total_default'] == $update['payment_total_default'] AND
    $order[0]['payment_fee_default'] == $update['payment_fee_default'] AND
    $order[0]['payment_due_default'] == $update['payment_due_default']
  ) {

    log_error("get_payment_default: but no changes, should have just called export_gd_update_invoice(). Could be caused by (1) order failing to create in WC (patient not available) or (2) order_item having wrong days/qty so Pharmacist deletes it and adds a new one really quickly (see order_item created but days_dispensed_actual already set)".$order[0]['order_stage_cp'], [$order, $update, $reason]);

  }

  foreach($order as $i => $item)
    $order[$i] = $update + $item;

  return $order;
}

function set_payment_default($order, $mysql) {

  $sql = "
    UPDATE
      gp_orders
    SET
      payment_total_default = {$order[0]['payment_total_default']},
      payment_fee_default   = {$order[0]['payment_fee_default']},
      payment_due_default   = {$order[0]['payment_due_default']},
      payment_date_autopay  = {$order[0]['payment_date_autopay']}
    WHERE
      invoice_number = {$order[0]['invoice_number']}
  ";

  $mysql->run($sql);
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
