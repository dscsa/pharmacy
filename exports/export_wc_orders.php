<?php

global $mysql;

function export_wc_delete_order($order) {

  $order_meta = wc_select_order($order[0]['invoice_number']);

  if ( ! $order_meta OR ! $order_meta[0]['post_id'])
    return log_error('export_wc_delete_order: no order exists with this invoice number', get_defined_vars());

  $sql1 = "DELETE FROM wp_posts WHERE id = ".$order_meta[0]['post_id'];
  $sql2 = "DELETE FROM wp_postmeta WHERE post_id = ".$order_meta[0]['post_id'];

  log_notice("export_wc_delete_order", get_defined_vars());//.print_r($item, true);
}

function wc_select_order($invoice_number) {

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $sql = "SELECT * FROM wp_posts JOIN wp_postmeta meta1 ON wp_posts.id = meta1.post_id JOIN wp_postmeta meta2 ON wp_posts.id = meta2.post_id WHERE meta1.meta_key='invoice_number' AND meta1.meta_value = '$invoice_number' ORDER BY wp_posts.id DESC";

  $order_meta = $mysql->run($sql);

  if ($order_meta)
    return $order_meta[0];

  log_notice('wc_select no matching order', get_defined_vars());
}

//Select or Insert
function wc_get_or_new_order($order) {

  $invoice_number = $order[0]['invoice_number'];
  $first_name = $order[0]['first_name'];
  $last_name = $order[0]['last_name'];
  $birth_date = $order[0]['birth_date'];

  $order_meta = wc_select_order($invoice_number);

  if ( ! $order_meta) {

    $response = wc_fetch("patient/$first_name $last_name $birth_date/order/$invoice_number");

    $order_meta = $response['order'];

    //These are the metadata that should NOT change
    wc_upsert($order_meta, 'shipping_method_id', ['31694']);
    wc_upsert($order_meta, 'shipping_method_title', ['31694' => 'Admin Fee']);
    wc_upsert($order_meta, 'patient_id_cp', $order[0]['patient_id_cp']);
    wc_upsert($order_meta, 'order_date_added', $order[0]['order_date_added']);
    wc_upsert($order_meta, 'refills_used', $order[0]['refills_used']);
    wc_upsert($order_meta, 'patient_autofill', $order[0]['patient_autofill']);
    wc_upsert($order_meta, 'order_source', $order[0]['order_source']);

    log_notice('wc_get_or_new_order: created new order', get_defined_vars());
  }

  return $order_meta;
}

function wc_insert($post_id, $meta_key, $meta_value) {

  if ( ! $meta_value) return;

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();
  $sql = "INSERT INTO wp_postmeta ('post_id', 'meta_key', 'meta_value') VALUES ('$post_id', '$meta_key', '$meta_value')";
  //log_notice('wc_insert', get_defined_vars());
  //$mysql->run($sql);
}

function wc_update($post_id, $meta_key, $meta_value) {

  if ( ! $meta_value) return;

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();
  $sql = "UPDATE wp_postmeta SET $meta_key = '$meta_value' WHERE post_id = $post_id";
  log_notice('wc_update', get_defined_vars());
  //$mysql->run($sql);
}

function wc_upsert($order_meta, $meta_key, $meta_value) {

  foreach ($order_meta as $meta) {
    if ($meta['meta_key'] == $meta_key) {
      if ($meta['meta_value'] == $meta_value)
        return; //log_notice('wc_upsert aborted because wc is already up to date', get_defined_vars());

      return wc_update($meta['post_id'], $meta_key, $meta_value);
    }
  }

  wc_insert($order_meta[0]['post_id'], $meta_key, $meta_value);
}

//These are the ones that might change
function export_wc_update_order_metadata($order) {

  $order_meta = wc_get_or_new_order($order);

  if ( ! $order_meta OR ! $order_meta[0]['post_id'])
    return log_error('export_wc_update_order_metadata: no order exists with this invoice number', get_defined_vars());

  //Native Fields
  if ($order[0]['payment_method'] == PAYMENT_METHOD['COUPON'])
    $payment_method = null;

  else if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
    $payment_method = 'stripe';

  else if ($order[0]['payment_method'] == PAYMENT_METHOD['MANUAL'])
    $payment_method = 'cheque';

  else
    log_error('export_wc_update_order_payment: update_order_payment: UNKNOWN Payment Method', get_defined_vars());

  wc_upsert($order_meta, '_payment_method', $payment_method);
  wc_upsert($order_meta, 'order_stage', $order[0]['order_stage']);
  wc_upsert($order_meta, 'order_status', $order[0]['order_status']);

  if ($order[0]['payment_coupon'])
    wc_upsert($order_meta, '_coupon_lines', [["code" => $order[0]['payment_coupon']]]);

  if ($order[0]['order_date_dispensed']) {
    wc_upsert($order_meta, 'order_date_dispensed', $order[0]['order_date_dispensed']);
    wc_upsert($order_meta, 'invoice_doc_id', $order[0]['invoice_doc_id']);
    wc_upsert($order_meta, 'count_items', $order[0]['count_items']);
    wc_upsert($order_meta, 'count_filled', $order[0]['count_filled']);
    wc_upsert($order_meta, 'count_nofill', $order[0]['count_nofill']);
  }

  if ($order[0]['tracking_number']) { //Keep status the same until it is shipped
    wc_upsert($order_meta, 'post_status', $order[0]['payment_method']);
    wc_upsert($order_meta, 'tracking_number', $order[0]['tracking_number']);
    wc_upsert($order_meta, 'order_date_shipped', $order[0]['order_date_shipped']);
  }
}

function export_wc_update_order_shipping($order) {

  $order_meta = wc_get_or_new_order($order);

  if ( ! $order_meta OR ! $order_meta[0]['post_id'])
    return log_error('export_wc_update_order_shipping: no order exists with this invoice number', get_defined_vars());

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

function export_wc_update_order_payment($invoice_number, $payment_fee) {

  $order_meta = wc_select_order($invoice_number);

  if ( ! $order_meta OR ! $order_meta[0]['post_id'])
    return log_error('export_wc_update_order_payment: no order exists with this invoice number', get_defined_vars());

  wc_upsert($order_meta, 'shipping_cost', ['31694' => $payment_fee]);
}


function wc_fetch($url, $method = 'GET', $content = []) {

  $opts = [
      /*
      "socket"  => [
        'bindto' => "0:$port",
      ],
      */
      "http" => [
        'method'  => $method,
        'content' => json_encode($content),
        'header'  => "Content-Type: application/json\r\n".
                     "Accept: application/json\r\n".
                     "Authorization: Basic ".base64_encode(WC_USER.':'.WC_PWD)
      ]
  ];

  $url = WC_URL."/wp-json/$url";

  $context = stream_context_create($opts);

  $res = file_get_contents($url, false, $context);

  $res_code = http_response_code();

  $res = json_decode($res, true);

  if ($res['error'])
    return log_error("wc_fetch", get_defined_vars());

  log_notice("wc_fetch", get_defined_vars());

  return $res;
}
