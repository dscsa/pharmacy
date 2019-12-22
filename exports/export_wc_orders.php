<?php

require_once 'helpers/helper_wc.php';


function export_wc_delete_order($item) {
  log_info("export_wc_delete_order", get_defined_vars());//.print_r($item, true);
}

function export_wc_update_order_metadata($order) {

  wc_fetch("orders/".$order[0]['invoice_number'], 'PUT', [
    "meta_data" => [
      ["key" => "invoice_number",       "value" => $order[0]['tracking_number']],
      ["key" => "patient_id_cp",        "value" => $order[0]['patient_id_cp']],
      ["key" => "tracking_number",      "value" => $order[0]['tracking_number']],
      ["key" => "order_date_added",     "value" => $order[0]['order_date_added']],
      ["key" => "order_date_dispensed", "value" => $order[0]['order_date_dispensed']],
      ["key" => "order_date_shipped",   "value" => $order[0]['order_date_shipped']],
      ["key" => "invoice_doc_id",       "value" => $order[0]['invoice_doc_id']]
    ]
  ]);
}

function export_wc_update_order_shipping($order) {

  wc_fetch("orders/".$order[0]['invoice_number'], 'PUT', [
    "shipping" => [
      ["key" => "first_name",  "value" => $order[0]['first_name']],
      ["key" => "last_name",   "value" => $order[0]['last_name']],
      ["key" => "email",       "value" => $order[0]['email']],
      ["key" => "phone",       "value" => $order[0]['phone1']],

      ["key" => "address_1",   "value" => $order[0]['patient_address1']],
      ["key" => "address_2",   "value" => $order[0]['patient_address2']],
      ["key" => "city",        "value" => $order[0]['patient_city']],
      ["key" => "state",       "value" => $order[0]['patient_state']],
      ["key" => "postcode",    "value" => $order[0]['patient_zip']]
    ]
  ]);
}

function export_wc_update_order_payment($order) {

  $update = [
    "status" => $order[0]['payment_method'],
    "shipping_lines" => [
      ["method_id" => "flat_rate", "total" => $order[0]['payment_fee']]
    ]
  ];

  //Debuggin
  $payment_method_key = $order[0]['payment_method'];
  $payment_method_val = PAYMENT_METHOD['AUTOPAY'];
  $payment_comparison = $order[0]['payment_method'].' == '.PAYMENT_METHOD['AUTOPAY'];

  if ($order[0]['payment_method'] == PAYMENT_METHOD['COUPON'])
    $update['coupon_lines'] = [["code" => $item['payment_coupon']]];

  else if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
    $update['payment_method'] = 'stripe';

  else if ($order[0]['payment_method'] == PAYMENT_METHOD['MANUAL'])
    $update['payment_method'] = 'cheque';

  else
    log_error('update_order_payment: UNKNOWN Payment Method', get_defined_vars());

  wc_fetch("orders/".$order[0]['invoice_number'], 'PUT', $update);
}
