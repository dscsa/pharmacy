<?php

global $mysql;

function wc_get_post_id($invoice_number) {
  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $sql = "SELECT * FROM wp_posts JOIN wp_postmeta ON wp_posts.id = wp_postmeta.post_id WHERE wp_postmeta.meta_key='invoice_number' AND wp_postmeta.meta_value = '$invoice_number'";
  $res = $mysql->run($sql);

  if (isset($res[0][0]))
    return $res[0][0]['post_id'];
}

function wc_insert_meta($invoice_number, $metadata) {

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $post_id = wc_get_post_id($invoice_number);

  if ( ! $post_id)
    return log_error('wc_insert_meta: no post id', get_defined_vars());

  foreach ($metadata as $meta_key => $meta_value) {
    if (is_array($meta_value))
      $meta_value = json_encode($meta_value);

    //mysql->run() does mysqli_query and not mysqli_multi_query so we cannot concatentate the inserts and run all at once
    $mysql->run("INSERT INTO wp_postmeta (meta_id, post_id, meta_key, meta_value) VALUES (NULL, '$post_id', '$meta_key', '$meta_value')");
  }

  log_info('wc_insert_meta', get_defined_vars());
}

//Avoid having duplicated meta_key(s) for a single order
function wc_update_meta($invoice_number, $metadata) {

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $post_id = wc_get_post_id($invoice_number);

  foreach ($metadata as $meta_key => $meta_value) {
    if (is_array($meta_value))
      $meta_value = json_encode($meta_value);

    //mysql->run() does mysqli_query and not mysqli_multi_query so we cannot concatentate the inserts and run all at once
    $mysql->run("UPDATE wp_postmeta SET meta_value = '$meta_value' WHERE post_id = $post_id AND meta_key = '$meta_key'");
  }

  log_info('wc_update_meta', get_defined_vars());
}

function wc_update_order($invoice_number, $orderdata) {

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $set = [];

  $post_id = wc_get_post_id($invoice_number);

  foreach ($orderdata as $order_key => $order_value) {

    if (is_array($order_value))
      $order_value = json_encode($order_value);

    $set[] = "$order_key = '$order_value'";
  }

  $sql = "
    UPDATE wp_posts SET ".implode(', ', $set)." WHERE ID = $post_id;
  ";

  log_notice('wc_update_order', get_defined_vars());

  $mysql->run($sql);

  if (@$orderdata['post_status']) {
    wc_insert_meta($invoice_number, ['status_update' => "Webform $orderdata[post_status] ".date('Y-m-d H:i:s')]);
  }
}

function export_wc_delete_order($invoice_number) {

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $post_id = wc_get_post_id($invoice_number);

  if ( ! $post_id)
    return log_error("export_wc_delete_order: cannot delete if no post_id", get_defined_vars());//.print_r($item, true);

  $sql1 = "DELETE FROM wp_postmeta WHERE post_id = $post_id";

  $mysql->run($sql1);

  $sql2 = "DELETE FROM wp_posts WHERE id = $post_id";

  $mysql->run($sql2);

  log_error("export_wc_delete_order", get_defined_vars());//.print_r($item, true);
}

function export_wc_create_order($order, $reason) {

  global $mysql;
  $mysql = $mysql ?: new Mysql_Wc();

  $invoice_number = $order[0]['invoice_number'];
  $first_name = str_replace('*', '', $order[0]['first_name']); //Ignore Cindy's internal marking
  $last_name = str_replace('*', '', $order[0]['last_name']); //Ignore Cindy's internal marking
  $birth_date = str_replace('*', '', $order[0]['birth_date']); //Ignore Cindy's internal marking

  $matches = $mysql->run("
    SELECT *
    FROM wp_posts
    JOIN wp_postmeta meta1 ON wp_posts.id = meta1.post_id
    WHERE meta1.meta_key='invoice_number'
    AND meta1.meta_value = '$invoice_number'
  ");

  if (count($matches[0])) {
    log_error("export_wc_create_order: aborting create order", [$first_name, $last_name, $invoice_number, $reason, $order[0]['order_stage_cp'], $order[0]['order_source'], $matches]);
    return $order;
  }

  //This creates order and adds invoice number to metadata
  //We do this through REST API because direct database calls seemed messy
  $url = "patient/$first_name $last_name $birth_date/order/$invoice_number";
  $res = wc_fetch($url);

  if ( ! empty($res['error']) AND empty($res['order'])) //if order is set, then its just a this order already exists error
    return log_error("export_wc_create_order: res[error] for $url", $res);

  //These are the metadata that should NOT change
  //wc_upsert_meta($order_meta, 'shipping_method_id', ['31694']);
  //wc_upsert_meta($order_meta, 'shipping_method_title', ['31694' => 'Admin Fee']);

  $metadata = [
    'patient_id_cp'     => $order[0]['patient_id_cp'],
    'order_date_added'  => $order[0]['order_date_added'],
    'refills_used'      => $order[0]['refills_used'],
    'patient_autofill'  => $order[0]['patient_autofill'],
    'order_source'      => $order[0]['order_source']
  ];

  wc_insert_meta($invoice_number, $metadata);
  export_wc_update_order_metadata($order, 'wc_insert_meta');
  export_wc_update_order_address($order, 'wc_insert_meta');
  export_wc_update_order_payment($invoice_number, $order[0]['payment_fee_default']);

  log_notice('export_wc_create_order: created new order', $metadata);

  return $order;
}

function export_wc_update_order($order) {
  export_wc_update_order_metadata($order);
  export_wc_update_order_address($order);
  export_wc_update_order_payment($order[0]['invoice_number'], $order[0]['payment_fee_default']);
}

//These are the metadata that might change
function export_wc_update_order_metadata($order, $meta_fn = 'wc_update_meta') {

  if ( ! $order[0]['order_stage_wc'])
    return log_error('export_wc_update_order_metadata: no order_stage_wc', get_defined_vars());

  $post_id = wc_get_post_id($order[0]['invoice_number']);

  if ( ! $post_id)
    return log_error('export_wc_update_order_metadata: order missing', get_defined_vars());

  $orderdata = [
    'post_status' => 'wc-'.$order[0]['order_stage_wc'] //,
    //'post_except' => $order[0]['order_note']
  ];

  log_notice('export_wc_update_order_metadata: wc_update_order', [
    'invoice_number' => $order[0]['invoice_number'],
    'order_stage_wc' => $order[0]['order_stage_wc'],
    'order_stage_cp' => $order[0]['order_stage_cp']
  ]);

  wc_update_order($order[0]['invoice_number'], $orderdata);

  $metadata = [
    '_payment_method' => $order[0]['payment_method'],
    'order_stage_cp'  => $order[0]['order_stage_cp'],
    'order_status'    => $order[0]['order_status'],
    'invoice_doc_id'  => $order[0]['invoice_doc_id']
  ];

  if ($order[0]['payment_coupon'])
    $metadata['_coupon_lines'] = [["code" => $order[0]['payment_coupon']]];

  if ($order[0]['order_date_dispensed']) {
    $metadata['order_date_dispensed'] = $order[0]['order_date_dispensed'];
    //$metadata['invoice_doc_id']       = $order[0]['invoice_doc_id'];
    $metadata['count_items']          = $order[0]['count_items'];
    $metadata['count_filled']         = $order[0]['count_filled'];
    $metadata['count_nofill']         = $order[0]['count_nofill'];
  }

  if ($order[0]['tracking_number']) { //Keep status the same until it is shipped
    $metadata['tracking_number']    = $order[0]['tracking_number'];
    $metadata['order_date_shipped'] = $order[0]['order_date_shipped'];
  }

  $meta_fn($order[0]['invoice_number'], $metadata);
}

function export_wc_update_order_address($order, $meta_fn = 'wc_update_meta') {

  $post_id = wc_get_post_id($order[0]['invoice_number']);

  if ( ! $post_id)
    return log_error('export_wc_update_order_shipping: order missing', get_defined_vars());

  $metadata = [
    '_shipping_first_name' => $order[0]['first_name'],
    '_shipping_last_name'  => $order[0]['last_name'],
    '_shipping_email'      => $order[0]['email'],
    '_shipping_phone'      => $order[0]['phone1'],
    '_billing_phone'       => $order[0]['phone2'],

    '_shipping_address_1'  => $order[0]['order_address1'],
    '_shipping_address_2'  => $order[0]['order_address2'],
    '_shipping_city'       => $order[0]['order_city'],
    '_shipping_state'      => $order[0]['order_state'],
    '_shipping_postcode'   => $order[0]['order_zip']
  ];

  $meta_fn($order[0]['invoice_number'], $metadata);
}

//Use REST API because direct DB calls to order_items and order_itemsmeta tables seemed messy/brittle
function export_wc_update_order_payment($invoice_number, $payment_fee) {

  $post_id = wc_get_post_id($invoice_number);

  if ( ! $post_id)
    return log_error('export_wc_update_order_payment: order missing', get_defined_vars());

  $response = wc_fetch("order/$post_id/payment_fee/$payment_fee");

  if ( ! $response)
    return log_error('export_wc_update_order_payment: failed', get_defined_vars());

  if ( ! empty($response['error']))
    return log_error('export_wc_update_order_payment: error', get_defined_vars());
}

function wc_fetch($path, $method = 'GET', $content = null, $retry = false) {

  $opts = [
      /*
      "socket"  => [
        'bindto' => "0:$port",
      ],
      */
      'http' => [
        'method'  => $method,
        'ignore_errors' => true,
        'timeout' => 2*60, //Seconds
        'header'  => "Content-Type: application/json\r\n".
                     "Accept: application/json\r\n".
                     "Authorization: Basic ".base64_encode(WC_USER.':'.WC_PWD)
      ]
  ];

  if ($content)
    $opts['http']['content'] = json_encode($content);

  $url = str_replace(' ', '%20', WC_URL."/wp-json/$path");

  $context = stream_context_create($opts);

  $res  = file_get_contents($url, false, $context);
  $json = json_decode($res, true);

  if ( ! $json AND ! $retry) {

    file_get_contents("https://postb.in/1579126559674-7982679251581?url=$url", false, $context);

    //CloudFlare sometimes returns a 400 Bad Request error even on valid url.  Tried Cloud Flare Page Rules but still having issues
    sleep(5);
    log_error("wc_fetch: no response attempt 1 of 2", ['url' => $url, 'res' => $res, 'http_code' => $http_response_header]);
    return wc_fetch($path, $method, $content, true);
  }
  else if ( ! $json) {

    log_error("wc_fetch: no response attempt 2 of 2", ['url' => $url, 'res' => $res, 'http_code' => $http_response_header]);
    return ['error' => "no response from wc_fetch"];
  }

  log_info("wc_fetch", ['url' => $url, 'json' => $json]);

  return $json;
}
