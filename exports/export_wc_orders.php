<?php

function export_wc_delete_order($item) {
  log_info("export_wc_delete_order", get_defined_vars());//.print_r($item, true);
}

function export_wc_update_order_metadata($item) {

  wc_fetch("orders/$item[invoice_number]", 'PUT', [
    "meta_data" => [
      ["key" => "invoice_number",       "value" => $item['tracking_number']],
      ["key" => "patient_id_cp",        "value" => $item['patient_id_cp']],
      ["key" => "tracking_number",      "value" => $item['tracking_number']],
      ["key" => "order_date_added",     "value" => $item['order_date_added']],
      ["key" => "order_date_dispensed", "value" => $item['order_date_dispensed']],
      ["key" => "order_date_shipped",   "value" => $item['order_date_shipped']],
      ["key" => "invoice_doc_id",       "value" => $item['invoice_doc_id']]
    ]
  ]);
}

function export_wc_update_order_shipping($item) {

  wc_fetch("orders/$item[invoice_number]", 'PUT', [
    "shipping" => [
      ["key" => "first_name",  "value" => $item['first_name']],
      ["key" => "last_name",   "value" => $item['last_name']],
      ["key" => "email",       "value" => $item['email']],
      ["key" => "phone",       "value" => $item['phone1']],

      ["key" => "address_1",   "value" => $item['patient_address1']],
      ["key" => "address_2",   "value" => $item['patient_address2']],
      ["key" => "city",        "value" => $item['patient_city']],
      ["key" => "state",       "value" => $item['patient_state']],
      ["key" => "postcode",    "value" => $item['patient_zip']]
    ]
  ]);
}

function export_wc_update_order_payment($item) {

  $update = [
    "status" => $item['payment_method'],
    "shipping_lines" => [
      ["method_id" => "flat_rate", "total" => $item['payment_fee']]
    ]
  ];

  if ($item['payment_method'] == PAYMENT_METHOD['COUPON'])
    $update['coupon_lines'] = [["code" => $item['payment_coupon']];

  else if ($item['payment_method'] == PAYMENT_METHOD['AUTOPAY']) {
    $update['payment_method'] = 'stripe';

  else if ($item['payment_method'] == PAYMENT_METHOD['MANUAL']) {
    $update['payment_method'] = 'cheque';

  else
    log_error('update_order_payment: UNKNOWN Payment Method', get_defined_vars());

  wc_fetch("orders/$item[invoice_number]", 'PUT', $update);
}
