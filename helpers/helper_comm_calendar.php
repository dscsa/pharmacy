<?php


function order_dispensed_event($order, $email, $hours_to_wait) {

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Order Dispensed: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Dispensed', 'Order Failed', 'Needs Form']);

  $commArr = new_comm_arr($email);

  email('order_dispensed_event', $eventTitle, $commArr, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait);
}

function order_shipped_event($order, $email, $text) {

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Order Shipped: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Shipped', 'Order Dispensed', 'Order Failed', 'Needs Form']);

  $commArr = new_comm_arr($email, $text);

  email('order_shipped_event', $eventTitle, $commArr, $cancel, $order);

  create_event($eventTitle, $commArr);
}

function refill_reminder_event($order, $email, $text, $hours_to_wait, $hour_of_day = null) {
  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Refill Reminder: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  //$cancel = cancel_events_by_person($order['first_name'], $order['last_name'], $order['birth_date'], ['Refill Reminder'])

  $commArr = new_comm_arr($email, $text);

  email('refill_reminder_event', $eventTitle, $commArr, $hours_to_wait, $hour_of_day, $order); //$cancel

  create_event($eventTitle, $commArr, $hours_to_wait, $hour_of_day);
}

function autopay_reminder_event($order, $email, $text, $hours_to_wait, $hour_of_day = null) {
  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Autopay Reminder: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Autopay Reminder']);

  $commArr = new_comm_arr($email, $text);

  email('autopay_reminder_event', $eventTitle, $commArr, $hours_to_wait, $hour_of_day, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait, $hour_of_day);
}

function order_created_event($order, $email, $text, $hours_to_wait) {
  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Order Created: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Failed', 'Order Hold', 'No Rx', 'Needs Form']);

  $commArr = new_comm_arr($email, $text);

  email('order_created_event', $eventTitle, $commArr, $hours_to_wait, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait);
}

function transfer_requested_event($order, $email, $text, $hours_to_wait) {

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Transfer Requested: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx']);

  $commArr = new_comm_arr($email, $text);

  email('transfer_requested_event', $eventTitle, $commArr, $hours_to_wait, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait);
}

function order_hold_event($order, $email, $text, $hours_to_wait) {

  if ( ! isset($order[0]['invoice_number']))
    email('ERROR order_hold_event: indexes not set', $order);

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Order Hold: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx']);

  $commArr = new_comm_arr($email, $text);

  email('order_hold_event', $eventTitle, $commArr, $hours_to_wait, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait);
}

function order_updated_event($order, $email, $text, $hours_to_wait) {
  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Order Updated: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Created', 'Transfer Requested', 'Order Updated', 'Order Hold', 'No Rx', 'Needs Form', 'Order Failed']);

  $commArr = new_comm_arr($email, $text);

  email('order_updated_event', $eventTitle, $commArr, $hours_to_wait, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait);
}

function needs_form_event($order, $email, $text, $hours_to_wait, $hour_of_day = null) {

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Needs Form: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $commArr = new_comm_arr($email, $text);

  email('needs_form_event', $eventTitle, $commArr, $hours_to_wait, $hour_of_day, $order);

  create_event($eventTitle, $commArr, $hours_to_wait, $hour_of_day);
}

function no_rx_event($order, $email, $text, $hours_to_wait, $hour_of_day = null) {

  if ( ! isset($order[0]['invoice_number']))
    email('ERROR no_rx_event: indexes not set', $order);

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' No Rx: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['No Rx']);

  $commArr = new_comm_arr($email, $text);

  email('no_rx_event', $eventTitle, $commArr, $hours_to_wait, $hour_of_day, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait, $hour_of_day);
}

function order_failed_event($order, $email, $text, $hours_to_wait, $hour_of_day  = null) {

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Order Failed: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date'], ['Order Failed']);

  $commArr = new_comm_arr($email, $text);

  email('order_failed_event', $eventTitle, $commArr, $hours_to_wait, $hour_of_day, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait, $hour_of_day);
}

function confirm_shipment_event($order, $email, $hours_to_wait, $hour_of_day = null) {

  $patientLabel = get_patient_label($order);
  $eventTitle   = $order[0]['invoice_number'].' Confirm Shipment: '.$patientLabel.'.  Created:'.date('Y:m:d H:i:s');

  $cancel = cancel_events_by_person($order[0]['first_name'], $order[0]['last_name'], $order[0]['birth_date']);

  $commArr = new_comm_arr($email);

  email('confirmShipmentEvent', $eventTitle, $commArr, $hours_to_wait, $hour_of_day, $cancel, $order);

  create_event($eventTitle, $commArr, $hours_to_wait, $hour_of_day);
}

function new_comm_arr($email, $text = '') {

  $commArr = [];

  if (LIVE_MODE AND $email AND ! preg_match('/\d\d\d\d-\d\d-\d\d@goodpill\.org/', $email['email'])) {
    $email['bcc']  = DEBUG_EMAIL;
    $email['from'] = 'Good Pill Pharmacy < support@goodpill.org >'; //spaces inside <> are so that google cal doesn't get rid of "HTML" if user edits description
    $commArr[] = $email;
  }

  if (LIVE_MODE AND $text AND $text['sms'] AND in_array($text['sms'], DO_NOT_SMS)) {
    //addCallFallback
    $json = preg_replace('/ undefined/g', '', json_encode($text));

    $text = format_text(json);
    $call = format_call(json);

    $call['message'] = 'Hi, this is Good Pill Pharmacy <Pause />'.$call['message'].' <Pause length="2" />if you need to speak to someone please call us at 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause length="2" /> Again our phone number is 8,,,,8,,,,8 <Pause />9,,,,8,,,,7 <Pause />5,,,,1,,,,8,,,,7. <Pause />';
    $call['call']    = $call['sms'];
    $call['sms']     = undefined;

    $text['fallbacks'] = [$call];
    $commArr[] = $text;
  }

  return $commArr; //just in case we were sloppy with undefined
}

function format_text($text_json) {

  $text_json = preg_replace(['/<br>/g', '/<.*?>/g', '/#(\d{4,})/g'], ['\\n', '', '$1'], $text_json);

  try {
    return json_decode($text_json, true);
  } catch (Error $e) {
    email('format_text json.parse error', $text_json, $e);
  }
}

function format_call($call_json) {

  $regex = [
    '/View it at [^ ]+ /',
    '/Track it at [^ ]+ /',
    '/\(?888[)-.]? ?987[.-]?5187/g',
    '/(www\.)?goodpill\.org/g',
    '/(\w):(?!\/\/)/g',
    '/;<br>/g',
    '/;|\./g,',
    '/(<br>)+/g',
    '/\.(\d)(\d)?(\d)?/g',
    '/ but /g',
    '/(\d+)MG/g',
    '/(\d+)MCG/g',
    '/(\d+)MCG/g',
    '/ Rxs/ig',
    '/ ER /ig',
    '/ DR /ig',
    '/ TAB| CAP/ig',
    '/\#(\d)(\d)(\d)(\d)(\d)(\d)?/'
  ];

  $replace = [
    "",
    "View and track your $order online at www.goodpill.org",
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
    email('format_call json.parse error', $call_json, $e);
  }
}

function get_patient_label($order) {
  return $order[0]['first_name'].' '.$order[0]['last_name'].' '.$order[0]['birth_date'];
}

function create_event($eventTitle, $commArr, $hours_to_wait = 0, $hour_of_day = null) {

  $startTime = get_start_time($hours_to_wait, $hour_of_day);

  $args = [
    'method'      => 'createCalendarEvent',
    'cal_id'      => GD_CAL_ID,
    'start'       => $startTime,
    'hours'       => 0.5,
    'title'       => $eventTitle,
    'description' => json_encode($commArr)
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  //email('create_event', $args, $result);
}

function cancel_events($ids) {

  $args = [
    'method'      => 'removeCalendarEvents',
    'cal_id'      => GD_CAL_ID,
    'ids'         => $ids
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  email('cancel_events', $args, $result);
}

function modify_events() {

  $args = [
    'method'  => 'modifyCalendarEvents',
    'cal_id'  => GD_CAL_ID,
    'events'  => $modify
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  email('modify_events', $args, $result);
}

//Return a copy of the date (or now) with the 24-hour set
function get_start_time($hours_to_wait, $hour_of_day) {

  $start = date('Y-m-d\TH:i:s', strtotime("+$hours_to_wait hours"));

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
    email('search_events', $args, $result);

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

    $new_desc = preg_replace('/'.implode(';|', $drugs).';/g', '', $old_desc);

    if ($old_desc == $new_desc) {
      continue;
    }

    if (str_pos($new_desc, ';') !== false) {
      $event['description'] = $new_desc;
      $modify[] = $event;
    }
    else {
      $remove[] = $event['id'];
    }
  }

  if (count($modify)) {
    email('remove_drugs_from_events modifyEvent', $modify, $first_name, $last_name, $birth_date, $drugs);
    modify_events($modify);
  }

  if (count($remove)) {
    email('remove_drugs_from_events removeEvent', $remove, $first_name, $last_name, $birth_date, $drugs);
    cancel_events($remove);
  }
}

function cancel_events_by_person($first_name, $last_name, $birth_date, $types) {

  if ( ! LIVE_MODE) return;

  $cancel = [];
  $events = search_events_by_person($first_name, $last_name, $birth_date, false, $types);

  foreach ($events as $event) {
    $cancel[] = $event['id'];
  }

  cancel_events($cancel);

  return $cancel;
}
