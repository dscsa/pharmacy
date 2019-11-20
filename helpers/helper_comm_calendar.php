<?php

//Internal communication warning an order was shipped but not dispensed.  Gets erased when/if order is shipped
function order_dispensed_notice($groups) {


  $days_ago = 2;
  $email   = [
    "email"   => PHARMACIST_EMAIL.','.DEBUG_EMAIL,
    "subject" => 'Warning Order #'.$groups['ALL'][0]['invoice_number'].' dispensed but not shipped'
  ];

  $email['message'] = implode('<br>', [

    $email['subject'].' '.$days_ago.' day ago. Please either add tracking number to guardian or erase the "Order Failed" event.'

  ]);

  order_dispensed_event($groups, $email, $days_ago*24);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function order_shipped_notice($groups) {

  //autopayReminderNotice(order, groups)

  $subject   = 'Your order '.($groups['NUM_FILLED'] ? 'of '.$groups['NUM_FILLED'].' items ' : '').'has shipped and should arrive in 3-5 days.';
  $message   = '';

  if ($groups['NUM_FILLED'])
    $message .= '<br><u>These Rxs are on the way:</u><br>'.implode(';<br>', $groups['FILL_ACTION'] + $groups['FILL_NOACTION']).';';

  if (count($groups['NOFILL_ACTION']))
    $message .= '<br><br><u>We cannot fill these Rxs without your help:</u><br>'.implode(';<br>', $groups['NOFILL_ACTION']).';';

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms"   => get_phones($groups['ALL']) ];

  $text['message'] =
    $subject.
    ($groups['ALL'][0]['invoice_doc_id'] ? ' View it at '.short_link('https://docs.google.com/document/d/'.$groups['ALL'][0]['invoice_doc_id'].'/pub?embedded=true').'. ' : '');
    'Track it at '.short_link(tracking_url($groups['ALL'][0]['tracking_number'])).'. '.
    $message;

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    'Thanks for choosing Good Pill Pharmacy. '.$subject,
    '',
    'Your receipt for order <strong>#'.$groups['ALL'][0]['invoice_number'].'</strong> is attached. Your tracking number is '.tracking_link($groups['ALL'][0]['tracking']).'.',
    'Use this link to request delivery notifications and/or provide the courier specific delivery instructions.',
    $message,
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ''
  ]);

  if ($groups['ALL'][0]['invoice_doc_id']) $email['attachments'] = [$groups['ALL'][0]['invoice_doc_id']];

  order_shipped_event($groups['ALL'], $email, $text);
}

function refill_reminder_notice($groups) {

  if ($groups['MIN_DAYS'] == INF OR ( ! count($groups['NO_REFILLS']) AND ! count($groups['NO_AUTOFILL']))) return;

  $subject  = 'Good Pill cannot refill these Rxs without your help.';
  $message  = '';

  if (count($groups['NO_REFILLS']))
    $message .= '<br><u>We need a new Rx for the following:</u><br>'.implode(';<br>', $groups['NO_REFILLS']).';';

  if (count($groups['NO_AUTOFILL']))
    $message .= '<br><br><u>These Rxs will NOT be filled automatically and must be requested 2 weeks in advance:</u><br>'.implode(';<br>', $groups['NO_AUTOFILL']).';';

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => getPhones($groups['ALL']), "message" => $subject.$message ];

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    'A friendly reminder that '.ucfirst($subject),
    $message,
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ''
  ]);

  refill_reminder_event($groups['ALL'], $email, $text, $groups['MIN_DAYS']*24, 12);
}

//Called from Webform so that we didn't have to repeat conditional logic
function autopay_reminder_notice($groups) {

  $subject  = "Autopay Reminder.";
  $message  = "Because you are enrolled in autopay, Good Pill Pharmacy will be be billing your card ".implode(' <Pause />', str_split($groups['ALL'][0]['payment_card_last4'])).' for $'.$groups['ALL'][0]['payment_fee'].".00. Please let us right away if your card has recently changed. Again we will be billing your card for $".$groups['ALL'][0]['payment_fee'].".00 for last month's Order #".$groups['ALL'][0]['invoice_number']." of ".$groups['NUM_FILLED']." items";

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => get_phones($groups['ALL']), "message" => $subject.' '.$message ];

  $text['message'] = $subject.' '.$message;

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    "Quick reminder that we are billing your card this week for last month's order.",
    $message,
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ''
  ]);

  $next_month = strtotime('+1 month');
  $time_wait  = $next_month - strtotime();

  autopay_reminder_event($groups['ALL'], $email, $text, $time_wait/60/60, 14);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function order_created_notice($groups) {

  $subject   = 'Good Pill is starting to prepare '.$groups['NUM_FILLED'].' items for Order #'.$groups['ALL'][0]['invoice_number'].'.';
  $message   = 'If your address has recently changed please let us know right away.';
  $drug_list = '<br><br><u>These Rxs will be included once we confirm their availability:</u><br>'.implode(';<br>', $groups['FILLED_WITH_PRICES']).';';

  if ( ! $groups['ALL'][0]['refills_used'])
    $message .= ' Your first order will only be $6 total for all of your medications.';

  if ($groups['NUM_NOFILL'])
    $drug_list .= '<br><br><u>We are NOT filling these Rxs:</u><br>'.implode(';<br>', $groups['NOFILL_NOACTION'] + $groups['NOFILL_ACTION']).';';

  $suffix = implode('<br><br>', [
    "Note: if this is correct, there is no need to do anything. If you want to change or delay this order, please let us know as soon as possible. If delaying, please specify the date on which you want it filled, otherwise if you don't, we will delay it 3 weeks by default."
  ]);

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => get_phones($groups['ALL']), "message" => $subject.' '.$message.$drug_list ];

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $subject.' We will notify you again once it ships. '.$message.$drug_list,
    '',
    ($groups['NUM_FILLED'] >= $groups['NUM_NOFILL']) ? 'Thanks for choosing Good Pill!' : 'Apologies for any inconvenience,',
    'The Good Pill Team',
    '',
    '',
    $suffix
  ]);

  //Remove Refill Reminders for new Rxs we just received Order #14512
  remove_drugs_from_events($groups['ALL'][0]['first_name'], $groups['ALL'][0]['last_name'], $groups['ALL'][0]['birth_date'], ['Refill Reminder'], $groups['FILLED']);

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  order_created_event($groups['ALL'], $email, $text, 15/60);
}

function transfer_requested_notice($groups) {

  $subject = 'Good Pill recieved your transfer request for Order #'.$groups['ALL'][0]['invoice_number'].'.';
  $message = 'We will notify you once we have contacted your pharmacy, '.$groups['ALL'][0]['pharmacy_name'].' '.$groups['ALL'][0]['pharmacy_address'].', and let you know whether the transfer was successful or not;';


  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => get_phones($groups['ALL']), "message" => $subject.' '.$message ];

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $subject,
    '',
    $message,
    '',
    'Thanks!',
    'The Good Pill Team'
  ]);

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  transfer_requested_event($groups['ALL'], $email, $text, 15/60);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function order_hold_notice($groups) {

  $subject = 'Good Pill is NOT filling your '.$groups['NUM_NOFILL'].' items for Order #'.$groups['ALL'][0]['invoice_number'].'.';
  $message = '<u>We are NOT filling these Rxs:</u><br>'.implode(';<br>', $groups['NOFILL_NOACTION'] + $groups['NOFILL_ACTION']).';';

  //['Not Specified', 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
  $trigger = '';

  if (in_array($groups['ALL'][0]['rx_source'], ["Not Specified", "SureScripts", "Fax", "Phone"]))
    $trigger = 'We got Rxs from your doctor via '.$groups['ALL'][0]['rx_source'].' but';
  else if (in_array($groups['ALL'][0]['rx_source'], ["Webform eRx", "eRx /w Note"]))
    $trigger = 'You successfully registered but';
  else if (in_array($groups['ALL'][0]['rx_source'], ["0 Refills"]))
    $trigger = 'We requested refills from your doctor but have not heard back so';
  else if (in_array($groups['ALL'][0]['rx_source'], ["Webform Refill", "Refill w/ Note"]))
    $trigger = 'We received your refill request but';

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => get_phones($groups['ALL']), "message" => $trigger.' '.$subject.' '.$message ];

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $trigger.' '.$subject,
    '',
    $message,
    '',
    'Apologies for any inconvenience,',
    'The Good Pill Team',
    '',
    '',
    "Note: if this is correct, there is no need to do anything. If you think there is a mistake, please let us know as soon as possible."
  ]);

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  order_hold_event($groups['ALL'], $email, $text, 15/60);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function order_updated_notice($groups) {

  //It's depressing to get updates if nothing is being filled.  So only send these if manually added and the order was just added (not just drugs changed)
  if ( ! $groups['NUM_FILLED'] AND ! $groups['MANUALLY_ADDED']) {
    $cancel = cancel_events($groups['ALL'][0]['first_name'], $groups['ALL'][0]['last_name'], $groups['ALL'][0]['birth_date'], ['Order Created', 'Order Updated', 'Order Hold', 'No Rx', 'Needs Form']);
    return email('order_updated_notice NOT sent', $groups);
  }

  $subject = 'Update for Order #'.$groups['ALL'][0]['invoice_number'].($groups['NUM_FILLED'] ? ' of '.$groups['NUM_FILLED'].' items.' : '');
  $message = '';

  if ($groups['NUM_FILLED'])
    $message .= '<br><u>These Rxs will be included once we confirm their availability:</u><br>'.implode(';<br>', $groups['FILLED_WITH_PRICES']).';';

  if ($groups['NUM_NOFILL'])
    $message .= '<br><br><u>We are NOT filling these Rxs:</u><br>'.implode(';<br>', $groups['NOFILL_NOACTION'] + $groups['NOFILL_ACTION']).';';

  $suffix = implode('<br><br>', [
    "Note: if this is correct, there is no need to do anything. If you want to change or delay this order, please let us know as soon as possible. If delaying, please specify the date on which you want it filled, otherwise if you don't, we will delay it 3 weeks by default."
  ]);

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => get_phones($groups['ALL']), "message" => $subject.$message ];

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $subject.' We will notify you again once it ships.',
    $message,
    '',
    ($groups['NUM_FILLED'] >= $groups['NUM_NOFILL']) ? 'Thanks for choosing Good Pill!' : 'Apologies for any inconvenience,',
    'The Good Pill Team',
    '',
    '',
    $suffix
  ]);

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  order_updated_event($groups['ALL'], $email, $text, 15/60);
}


function needs_form_notice($groups) {

  ///It's depressing to get updates if nothing is being filled
  if ($groups['NUM_FILLED']) {
    $subject = 'Welcome to Good Pill!  We are excited to fill your 1st Order.';
    $message = 'Your order will be #'.$groups['ALL'][0]['invoice_number'].". Please take 5mins to register so that we can fill the Rxs we got from your doctor as soon as possible. Once you register it will take 5-7 business days before you receive your order. You can register online at www.goodpill.org or by calling us at (888) 987-5187.<br><br><u>The drugs in your 1st order will be:</u><br>".implode(';<br>', $groups['FILLED_WITH_PRICES']).';';
  }
  else {
    $subject = "Welcome to Good Pill. Unfortunately we can't complete your 1st Order";
    $message = "We are very sorry for the inconvenience but we can't fill the Rx(s) in Order #".$groups['ALL'][0]['invoice_number']." that we received from your doctor. Please ask your local pharmacy to contact us to get the prescription OR register online or over the phone and let us know to which pharmacy we should transfer the Rx(s).<br><br>Because we rely on donated medicine, we can only fill medications that are listed here www.goodpill.org/gp-stock";
  }

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms"   => get_phones($groups['ALL']), "message" => $subject.' '.$message ];

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $subject.' '.$message,
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ''
  ]);

  //By basing on added at, we remove uncertainty of when script was run relative to the order being added
  $hour_added = substr($groups['ALL'][0]['order_date_added'], 11, 2); //get hours

  if($hour_added < 10){
    //A if before 10am, the first one is at 10am, the next one is 5pm, then 10am tomorrow, then 5pm tomorrow
    $hours_to_wait = [0, 0, 24, 24, 24*7, 24*14];
    $hour_of_day   = [11, 17, 11, 17, 17, 17];

  } else if ($hour_added < 17){
    //A if before 5pm, the first one is 10mins from now, the next one is 5pm, then 10am tomorrow, then 5pm tomorrow
    $hours_to_wait = [10/60, 0, 24, 24, 24*7, 24*14];
    $hour_of_day   = [null, 17, 11, 17, 17, 17];

  } else {
    //B if after 5pm, the first one is 10am tomorrow, 5pm tomorrow, 10am the day after tomorrow, 5pm day after tomorrow.
    $hours_to_wait = [24, 24, 48, 48, 24*7, 24*14];
    $hour_of_day   = [11, 17, 11, 17, 17, 17];
  }

  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[0], $hour_of_day[0]);

  if ( ! $groups['NUM_FILLED']) return; //Don't hassle folks if we aren't filling anything

  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[1], $hour_of_day[1]);
  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[2], $hour_of_day[2]);
  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[3], $hour_of_day[3]);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function no_rx_notice($groups) {

  $subject = 'Good Pill received Order #'.$groups['ALL'][0]['invoice_number'].' but is waiting for your prescriptions';
  $message  = ($groups['ALL'][0]['order_source'] == 'Webform Transfer' OR $groups['ALL'][0]['order_source'] == 'Transfer w/ Note')
    ? "We will attempt to transfer the Rxs you requested from, ".$groups['ALL'][0]['pharmacy_name'].' '.$groups['ALL'][0]['pharmacy_address'].'.';
    : "We haven't gotten any Rxs from your doctor yet but will notify you as soon as we do.";

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms"   => get_phones($groups['ALL']), $message => $subject.'. '.$message ];

  $email['subject'] = $subject;
  $email['message']  = implode('<br>', [
    'Hello,',
    '',
    $subject.'. '.$message,
    '',
    '',
    'Thanks,',
    'The Good Pill Team',
    '',
    '',
    "Note: if this is correct, there is no need to do anything. If you think there is a mistake, please let us know as soon as possible."
  ]);

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  no_rx_event($groups['ALL'], $email, $text, 15/60);
}

function order_failed_notice($groups, $days) {

  $subject  = "Apologies but Good Pill is having trouble with your Order #".$groups['ALL'][0]['invoice_number'];

  if ($groups['NUM_FILLED'])
    $message = "We are so sorry for the inconvenience. Please call us at (888) 987-5187 and we will explain the issue.";
  else if ($groups['ALL'][0]['order_source'] == 'Webform Transfer' OR $groups['ALL'][0]['order_source'] == 'Transfer w/ Note')
    $message = "We were unable to transfer the Rxs you requested from, ".$groups['ALL'][0]['pharmacy_name'].' '.$groups['ALL'][0]['pharmacy_address'].". This usually happens because we have the wrong pharmacy on file, we are requesting the wrong Rxs, or your Rxs have no refills remaining";
  else
    $message = "We haven't gotten any Rxs from your doctor yet. You may want to contact your doctor.  If you had meant for us to transfer Rxs from your pharmacy instead, please login to your account and place a new 'transfer' order or give us a call at (888) 987-5187.";

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => get_phones($groups['ALL']),  "message" => $subject.'. '.$message ];

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $subject.'. '.$message,
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ''
  ]);

  order_failed_event($groups['ALL'], $email, $text, $days*24, 13);
  order_failed_event($groups['ALL'], [
    "email"   => CINDY_EMAIL.','.DEBUG_EMAIL,
    "subject" => 'To Be Sent Tomorrow: '.$subject,
    "message" => 'To Be Sent Tomorrow: '.$email.$message
  ], null, ($days-1)*24, 13);
}

function confirm_shipment_notice($groups) {
  confirm_shipping_internal($groups);
  confirm_shipping_external($groups);
}

function confirm_shipping_internal($groups) {

  if ( ! $groups['ALL'][0]['refills_used']) return;

  ///It's depressing to get updates if nothing is being filled
  $subject  = "Follow up on new patient's first order";
  $days_ago = 5;

  $email = [ "email" => 'support@goodpill.org' ];

  $email['subject'] = $subject;
  $email['message'] = implide('<br>', [
    'Hello,',
    '',
    $groups['ALL'][0]['first_name'].' '.$groups['ALL'][0]['last_name'].' '.$groups['ALL'][0]['birth_date'].' is a new patient.  They were shipped Order #'.$groups['ALL'][0]['invoice_number'].' with '.$groups['NUM_FILLED'].' items '.$days_ago.' days ago.',
    '',
    'Please call them at '.$groups['ALL'][0]['phone1'].', '.$groups['ALL'][0]['phone2'].' and check on the following:',
    '- Order with tracking number '.tracking_link($groups['ALL'][0]['tracking_number']).' was delivered and that they received it',
    '',
    '- Make sure they got all '.$groups['NUM_FILLED'].' of their medications, that we filled the correct number of pills, and answer any questions the patient has',
    $groups['NUM_NOFILL'] ? '<br>- Explain why we did NOT fill:<br>'.implode(';<br>', $groups['NOFILL_NOACTION'] + $groups['NOFILL_ACTION']).'<br>' : '',
    '- Let them know they are currently set to pay via '.$groups['ALL'][0]['payment_method'].' and the cost of the '.$groups['NUM_FILLED'].' items was $'.$groups['ALL'][0]['payment_fee'].' this time, but next time it will be $'.$groups['ALL'][0]['payment_total'],
    '',
    '- Review their current medication list and remind them which prescriptions we will be filling automatically and which ones they need to request 2 weeks in advance',
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ''
  ]);

  confirm_shipment_event($groups['ALL'], $email, $days_ago*24, 13);
}

function confirm_shipping_external($groups) {

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms"   => get_phones($groups['ALL']) ];

  $subject = "Order #".$groups['ALL'][0]['invoice_number']." was delivered.";
  $message = " should have been delivered within the past few days.  Please contact us at 888.987.5187 if you have not yet received your order.";

  $text['message'] = $subject.' Your order with tracking number '.$groups['ALL'][0]['tracking_number'].$message;

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $subject.' Your order with tracking number '.tracking_link($groups['ALL'][0]['tracking_number']).$message,
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ''
  ]);

  confirm_shipment_event($groups['ALL'], $email, 7*24, 14);
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
  return $order[0]['phone1'].($order[0]['phone2'] ? ','.$order[0]['phone2'] : '');
}
