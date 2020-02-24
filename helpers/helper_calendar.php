<?php


function order_dispensed_event($order, $email, $hours_to_wait) {

  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Order Dispensed: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Dispensed', 'Order Canceled', 'Needs Form']);

  $comm_arr = new_comm_arr($email);

  log_info('order_dispensed_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait);
}

function order_shipped_event($order, $email, $text) {

  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Order Shipped: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Shipped', 'Order Dispensed', 'Order Canceled', 'Needs Form']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('order_shipped_event', get_defined_vars());

  create_event($event_title, $comm_arr);
}

function refill_reminder_event($order, $email, $text, $hours_to_wait, $hour_of_day = null) {
  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Refill Reminder: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  //$cancel = cancel_events_by_person($order['first_name'], $order['last_name'], $order['birth_date'], ['Refill Reminder'])

  $comm_arr = new_comm_arr($email, $text);

  log_info('refill_reminder_event', get_defined_vars()); //$cancel

  create_event($event_title, $comm_arr, $hours_to_wait, $hour_of_day);
}

function autopay_reminder_event($order, $email, $text, $hours_to_wait, $hour_of_day = null) {
  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Autopay Reminder: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Autopay Reminder']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('autopay_reminder_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait, $hour_of_day);
}

function order_created_event($order, $email, $text, $hours_to_wait) {
  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Order Created: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Canceled', 'Order Hold', 'No Rx', 'Needs Form']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('order_created_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait);
}

function transfer_requested_event($order, $email, $text, $hours_to_wait) {

  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Transfer Requested: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('transfer_requested_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait);
}

function order_hold_event($order, $email, $text, $hours_to_wait) {

  if ( ! isset($order[0]['invoice_number']))
    log_error('ERROR order_hold_event: indexes not set', get_defined_vars());

  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Order Hold: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('order_hold_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait);
}

function order_updated_event($order, $email, $text, $hours_to_wait) {
  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Order Updated: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx', 'Needs Form', 'Order Canceled']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('order_updated_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait);
}

function needs_form_event($order, $email, $text, $hours_to_wait, $hour_of_day = 0) {

  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Needs Form: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Needs Form']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('needs_form_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait, $hour_of_day);
}

function no_rx_event($order, $email, $text, $hours_to_wait, $hour_of_day = null) {

  if ( ! isset($order[0]['invoice_number']))
    log_error('ERROR no_rx_event: indexes not set', get_defined_vars());

  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' No Rx: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['No Rx']);

  $comm_arr = new_comm_arr($email, $text);

  log_info('no_rx_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait, $hour_of_day);
}

function order_canceled_event($order, $email, $text, $hours_to_wait, $hour_of_day  = null) {

  $event_title   = $order[0]['invoice_number'].' Order Canceled. Created:'.date('Y:m:d H:i:s');

  $comm_arr = new_comm_arr($email, $text);

  log_info('order_canceled_event', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait, $hour_of_day);
}

function confirm_shipment_event($order, $email, $hours_to_wait, $hour_of_day = null) {

  $patient_label = get_patient_label($order);
  $event_title   = $order[0]['invoice_number'].' Confirm Shipment: '.$patient_label.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Dispensed', 'Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx', 'Needs Form', 'Order Canceled']);

  $comm_arr = new_comm_arr($email);

  log_info('confirmShipmentEvent', get_defined_vars());

  create_event($event_title, $comm_arr, $hours_to_wait, $hour_of_day);
}

function new_comm_arr($email, $text = '') {

  $comm_arr = [];

  if (LIVE_MODE AND $email AND ! preg_match('/\d\d\d\d-\d\d-\d\d@goodpill\.org/', $email['email'])) {
    $email['bcc']  = DEBUG_EMAIL;
    $email['from'] = 'Good Pill Pharmacy < support@goodpill.org >'; //spaces inside <> are so that google cal doesn't get rid of "HTML" if user edits description
    $comm_arr[] = $email;
  }

  if (LIVE_MODE AND $text AND $text['sms'] AND ! in_array($text['sms'], DO_NOT_SMS)) {
    //addCallFallback
    $json = preg_replace('/ undefined/', '', json_encode($text));

    $text = format_text($json);
    $call = format_call($json);

    $call['message'] = 'Hi, this is Good Pill Pharmacy <Pause />'.$call['message'].' <Pause length="2" />if you need to speak to someone please call us at 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause length="2" /> Again our phone number is 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause />';
    $call['call']    = $call['sms'];
    unset($call['sms']);

    log_info('comm_array', get_defined_vars());

    $text['fallbacks'] = [$call];
    $comm_arr[] = $text;
  }

  return $comm_arr; //just in case we were sloppy with undefined
}

function format_text($text_json) {

  $text_json = preg_replace(['/<br>/', '/<.*?>/', '/#(\d{4,})/'], ['\\n', '', '$1'], $text_json);

  try {
    return json_decode($text_json, true);
  } catch (Error $e) {
    log_error('format_text json.parse error', get_defined_vars());
  }
}

function format_call($call_json) {

  $regex = [
    '/View it at [^ ]+ /',
    '/Track it at [^ ]+ /',
    '/\(?888[)-.]? ?987[.-]?5187/',
    '/(www\.)?goodpill\.org/',
    '/(\w):(?!\/\/)/',
    '/;<br>/',
    '/;|\./',
    '/(<br>)+/',
    '/\.(\d)(\d)?(\d)?/',
    '/ but /',
    '/(\d+)MG/',
    '/(\d+)MCG/',
    '/(\d+)MCG/',
    '/ Rxs/i',
    '/ ER /i',
    '/ DR /i',
    '/ TAB| CAP/i',
    '/\#(\d)(\d)(\d)(\d)(\d)(\d)?/'
  ];

  $replace = [
    "",
    "View and track your order online at www.goodpill.org",
    '8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7',
    'w,,w,,w,,dot,,,,good,,,,pill,,,,dot,,,,org,,,,again that is g,,,,o,,,,o,,,d,,,,p,,,,i,,,,l,,,,l,,,,dot,,,,o,,,,r,,,,g',
    '$1<Pause />', //Don't capture JSON $text or URL links
    '<Pause /> and <Pause />', //combine drug list with "and" since it sounds more natural
    ' <Pause />', //can't do commas without testing for inside quotes because that is part of json syntax
    ' <Pause length=\\"1\\" />',
    ' point $1,,$2,,$3', //skips pronouncing decimal points
    ',,,,but,,,,',
    '<Pause />$1 milligrams',
    '<Pause />$1 micrograms',
    '<Pause />$1 micrograms',
    ' prescriptions',
    ' extended release ',
    ' delayed release ',
    ' <Pause />',
    'number,,,,$1,,$2,,$3,,$4,,$5,,$6' //<Pause /> again that is $order number <Pause />$1,,$2,,$3,,$4,,$5,,$6
  ];

  //Improve Pronounciation
  $call_json = preg_replace($regex, $replace, $call_json);

  try {
    return json_decode($call_json, true);
  } catch (Error $e) {
    log_error('format_call json.parse error', get_defined_vars());
  }
}

function get_patient_label($order) {

  if ( ! isset($order[0])) {
    log_error('ERROR: get_patient_label', get_defined_vars());
    return '';
  }

  return $order[0]['first_name'].' '.$order[0]['last_name'].' '.$order[0]['birth_date'];
}

function create_event($event_title, $comm_arr, $hours_to_wait = 0, $hour_of_day = null) {

  $startTime = get_start_time($hours_to_wait, $hour_of_day);

  $args = [
    'method'      => 'createCalendarEvent',
    'cal_id'      => GD_CAL_ID,
    'start'       => $startTime,
    'hours'       => 0.5,
    'title'       => "(NEW) $event_title",
    'description' => $comm_arr
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

}

function cancel_events($ids) {

  $args = [
    'method'      => 'removeCalendarEvents',
    'cal_id'      => GD_CAL_ID,
    'ids'         => $ids
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_info('cancel_events', get_defined_vars());
}

function modify_events($modify) {

  $args = [
    'method'  => 'modifyCalendarEvents',
    'cal_id'  => GD_CAL_ID,
    'events'  => $modify
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_info('modify_events', get_defined_vars());
}

function short_links($links) {

  $args = [
    'method'  => 'shortLinks',
    'links'  => $links
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_info('modify_events', get_defined_vars());
}

function tracking_url($tracking_number) {

  $url = '#';

  if (strlen($tracking_number) == 22) {
    $url = 'https://tools.usps.com/go/TrackConfirmAction?tLabels=';
  } else if (strlen($tracking_number) == 15 OR strlen($tracking_number) == 12) { //Ground or Express
    $url = 'https://www.fedex.com/apps/fedextrack/?tracknumbers=';
  }

  return $url.$tracking_number;
}

//Convert gsheet hyperlink formula to an html link
function tracking_link($tracking) {
  return '<a href="'.tracking_url($tracking).'">'.$tracking.'</a>';
}

function get_phones($order) {

  if ( ! isset($order[0])) {
    log_error('get_phones', get_defined_vars());
    return '';
  }
  //email('get_phones', $order);
  return $order[0]['phone1'].($order[0]['phone2'] ? ','.$order[0]['phone2'] : '');
}

//Return a copy of the date (or now) with the 24-hour set
function get_start_time($hours_to_wait, $hour_of_day) {

  //PHP Issue of strtotime() with fractions https://stackoverflow.com/questions/11086022/can-strtotime-handle-fractions so convert to minutes and round
  $minutes_to_wait = round($hours_to_wait*60);

  $start = date('Y-m-d\TH:i:s', strtotime("+$minutes_to_wait minutes"));

  if ($hour_of_day) {
    $start = substr($start, 0, 11)."$hour_of_day:00:00";
  }

  return $start;
}

//TODO exactly replicate Guardian's patient matching function
function search_events_by_person($first_name, $last_name, $birth_date, $past = false, $types = []) {

  $types = implode('|', $types);
  $first_name = substr($first_name, 0, 3);

  $args = [
    'method'       => 'searchCalendarEvents',
    'cal_id'       => GD_CAL_ID,
    'hours'        => 90*24,
    'past'         => $past,
    'word_search'  => "$last_name $birth_date",
    'regex_search' => "/($types).+$first_name/i" //first name is partial which is not currently supported by gcal natively
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  if ($result != '[]')
    log_error('search_events', get_defined_vars());

  return json_decode($result, true);
}

//NOTE: RELIES on the assumption that ALL drugs (and their associated messages) end with a semicolon (;) and
//that NO other semicolons are used for any other reason. It removes everything between the drug name and the
//semicolon, and if no semicolons are left in the communication, then the entire communication is deleted
function remove_drugs_from_events($first_name, $last_name, $birth_date, $types, $drugs) {

  if ( ! LIVE_MODE) return;

  $modify = [];
  $remove = [];
  $events = search_events_by_person($first_name, $last_name, $birth_date, false, $types);

  foreach ($events as $event) {
    $old_desc = $event['description']; //This is still JSON.stringified

    $new_desc = preg_replace('/'.implode(';|', $drugs).';/', '', $old_desc);

    if ($old_desc == $new_desc) {
      continue;
    }

    if (strpos($new_desc, ';') !== false) {
      $event['description'] = $new_desc;
      $modify[] = $event;
    }
    else {
      $remove[] = $event['id'];
    }
  }

  if (count($modify)) {
    log_info('remove_drugs_from_events modifyEvent', get_defined_vars());
    modify_events($modify);
  }

  if (count($remove)) {
    log_info('remove_drugs_from_events removeEvent', get_defined_vars());
    cancel_events($remove);
  }
}

function cancel_events_by_person($first_name, $last_name, $birth_date, $types = []) {

  if ( ! LIVE_MODE) return;

  $cancel = [];
  $events = search_events_by_person($first_name, $last_name, $birth_date, false, $types);

  if ( ! is_array($events)) {
    log_error('cancel_events_by_person', get_defined_vars());
    $events = [];
  }

  foreach ($events as $event) {
    $cancel[] = $event['id'];
  }

  if ($cancel)
    cancel_events($cancel);

  return $cancel;
}
