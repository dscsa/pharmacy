<?php

global $mysql;

function export_wc_delete_order($item) {
  log_info("export_wc_delete_order", get_defined_vars());//.print_r($item, true);
}

function wc_select($invoice_number) {

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $sql = "SELECT * FROM wp_posts JOIN wp_postmeta meta1 ON wp_posts.id = meta1.post_id JOIN wp_postmeta meta2 ON wp_posts.id = meta2.post_id WHERE meta2.meta_key='invoice_number' AND meta2.meta_value = '$invoice_number' ORDER BY wp_posts.id DESC";

  $order_meta = $mysql->run($sql);

  if ($order_meta)
    return $order_meta[0];

  log_error('wc_select no matching order', get_defined_vars());
}

function wc_insert($post_id, $meta_key, $meta_value) {
  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();
  $sql = "INSERT INTO wp_postmeta ('post_id', 'meta_key', 'meta_value') VALUES ('$post_id', '$meta_key', '$meta_value')";
  log_notice('wc_insert', get_defined_vars());
  //$mysql->run($sql);
}

function wc_update($post_id, $meta_key, $meta_value) {
  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();
  $sql = "UPDATE wp_postmeta SET $meta_key = '$meta_value' WHERE post_id = $post_id";
  log_notice('wc_update', get_defined_vars());
  //$mysql->run($sql);
}

function wc_upsert($order_meta, $meta_key, $meta_value) {

  if ( ! $order_meta)
    log_error('no order exists with this invoice number', get_defined_vars());

  else if ( ! isset($order_meta[$meta_key]))
    wc_insert($order_meta['post_id'], $meta_key, $meta_value);

  else if ($order_meta[$meta_key] != $meta_value)
    wc_update($order_meta['post_id'], $meta_key, $meta_value);

  else
    log_notice('wc_upsert unneccessary value is already correct', get_defined_vars());
}

function export_wc_update_order_metadata($order) {

  $order_meta = wc_select($order[0]['invoice_number']);

  wc_upsert($order_meta, 'tracking_number', $order[0]['tracking_number']);
  wc_upsert($order_meta, 'patient_id_cp', $order[0]['patient_id_cp']);
  wc_upsert($order_meta, 'order_date_added', $order[0]['order_date_added']);
  wc_upsert($order_meta, 'order_date_dispensed', $order[0]['order_date_dispensed']);
  wc_upsert($order_meta, 'order_date_shipped', $order[0]['order_date_shipped']);
  wc_upsert($order_meta, 'invoice_doc_id', $order[0]['invoice_doc_id']);
  wc_upsert($order_meta, 'count_items', $order[0]['count_items']);
  wc_upsert($order_meta, 'count_filled', $order[0]['count_filled']);
  wc_upsert($order_meta, 'count_nofill', $order[0]['count_nofill']);
  wc_upsert($order_meta, 'order_source', $order[0]['order_source']);
  wc_upsert($order_meta, 'order_stage', $order[0]['order_stage']);
  wc_upsert($order_meta, 'order_status', $order[0]['order_status']);
  wc_upsert($order_meta, 'refills_used', $order[0]['refills_used']);
  wc_upsert($order_meta, 'patient_autofill', $order[0]['patient_autofill']);
}

function export_wc_update_order_shipping($order) {

  $order_meta = wc_select($order[0]['invoice_number']);

  wc_upsert($order_meta, '_shipping_first_name', $order[0]['first_name']);
  wc_upsert($order_meta, '_shipping_last_name', $order[0]['last_name']);
  wc_upsert($order_meta, '_shipping_email', $order[0]['email']);
  wc_upsert($order_meta, '_shipping_phone', $order[0]['phone1']);
  wc_upsert($order_meta, '_billing_phone', $order[0]['phone2']);


  wc_upsert($order_meta, '_shipping_address_1', $order[0]['order_address1']);
  wc_upsert($order_meta, '_shipping_address_2', $order[0]['order_address2']);
  wc_upsert($order_meta, '_shipping_city', $order[0]['order_city']);
  wc_upsert($order_meta, '_shipping_state', $order[0]['order_state']);
  wc_upsert($order_meta, '_shipping_postcode', $order[0]['order_zip']);
}

function export_wc_update_order_payment($order) {

  $update = [
    "status" => $order[0]['payment_method'],
    "shipping_lines" => [
      ["method_id" => "flat_rate", "total" => $order[0]['payment_fee']]
    ]
  ];

  if ($order[0]['payment_method'] == PAYMENT_METHOD['COUPON'])
    $payment_method = null;

  else if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
    $payment_method = 'stripe';

  else if ($order[0]['payment_method'] == PAYMENT_METHOD['MANUAL'])
    $payment_method = 'cheque';

  else
    log_error('update_order_payment: UNKNOWN Payment Method', get_defined_vars());

  $order_meta = wc_select($order[0]['invoice_number']);

  wc_upsert($order_meta, 'shipping_method_id', ['31694']);
  wc_upsert($order_meta, 'shipping_method_title', ['31694' => 'Admin Fee']);
  wc_upsert($order_meta, 'shipping_cost', ['31694' => $order[0]['payment_fee']]);
  wc_upsert($order_meta, 'post_status', $order[0]['payment_method']);
  wc_upsert($order_meta, '_payment_method', $payment_method);
  wc_upsert($order_meta, '_coupon_lines', [["code" => $order[0]['payment_coupon']]]);
}
