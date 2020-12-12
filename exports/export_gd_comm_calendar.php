<?php

use Sirum\Logging\SirumLog;

require_once 'helpers/helper_calendar.php';

//Internal communication warning an order was shipped but not dispensed.  Gets erased when/if order is shipped
function order_dispensed_notice($groups) {

  $days_ago = 2;

  $salesforce = [
    "subject" => 'Warning Order #'.$groups['ALL'][0]['invoice_number'].' dispensed but not shipped',
    "body" => "If shipped, please add tracking number to Guardian Order.  If not shipped, check comm-calendar and see if we need to inform patient that order was delayed or canceled.",
    "contact" => $groups['ALL'][0]['first_name'].' '.$groups['ALL'][0]['last_name'].' '.$groups['ALL'][0]['birth_date'],
    "assign_to" => ".Delay/Expedite Order - RPh",
    "due_date" => date('Y-m-d')
  ];

  order_dispensed_event($groups['ALL'], $salesforce, $days_ago*24);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function order_shipped_notice($groups) {

  //autopayReminderNotice(order, groups)

  $subject   = 'Good Pill shipped order '.($groups['COUNT_FILLED'] ? 'of '.$groups['COUNT_FILLED'].' items ' : '').' and it should arrive in 3-5 days.';
  $message   = '';

  $message .= '<br><u>These Rxs are on the way:</u><br>'.implode(';<br>', $groups['FILLED']).';';

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms"   => get_phones($groups['ALL']) ];

  $links = short_links([
    'invoice'       => 'https://docs.google.com/document/d/'.$groups['ALL'][0]['invoice_doc_id'].'/pub?embedded=true',
    'tracking_url'  => tracking_url($groups['ALL'][0]['tracking_number'])
  ]);

  $text['message'] =
    $subject.
    $message.
    ' View it at '.$links['invoice'].'. '.
    'Track it at '.$links['tracking_url'].'. ';

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    'Thanks for choosing Good Pill Pharmacy. '.$subject,
    '',
    'Your receipt for Order #'.$groups['ALL'][0]['invoice_number'].' is attached. Your tracking number is '.tracking_link($groups['ALL'][0]['tracking_number']), //<strong>#'.$groups['ALL'][0]['invoice_number'].'</strong> .$links['tracking_link'].'.',
    'Use this link to request delivery notifications and/or provide the courier specific delivery instructions.',
    $message,
    '',
    'Thanks!',
    'The Good Pill Team',
    '',
    ! count($groups['NOFILL_ACTION']) ? '' : '<br><u>We cannot fill these Rxs without your help:</u><br>'.implode(';<br>', $groups['NOFILL_ACTION']).';',
    ''
  ]);

  if ($groups['ALL'][0]['invoice_doc_id'])
    $email['attachments'] = [$groups['ALL'][0]['invoice_doc_id']];

  log_info('order_shipped_notice', get_defined_vars());

  order_shipped_event($groups['ALL'], $email, $text);
}

function refill_reminder_notice($groups) {

  if ($groups['MIN_DAYS'] == 366 OR ( ! count($groups['NO_REFILLS']) AND ! count($groups['NO_AUTOFILL']))) {
    log_notice("Not making a refill_reminder_notice", $groups);
    return;
  }

  $subject  = 'Good Pill cannot refill these Rxs without your help.';
  $message  = '';

  if (count($groups['NO_REFILLS']))
    $message .= '<br><u>We need a new Rx for the following:</u><br>'.implode(';<br>', $groups['NO_REFILLS']).';';

  if (count($groups['NO_AUTOFILL']))
    $message .= '<br><br><u>These Rxs will NOT be filled automatically and must be requested 2 weeks in advance:</u><br>'.implode(';<br>', $groups['NO_AUTOFILL']).';';

  $email = [ "email" => $groups['ALL'][0]['email'] ];
  $text  = [ "sms" => get_phones($groups['ALL']), "message" => $subject.$message ];

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

  log_error("refill_reminder_notice is this right?", [$groups, $email]);
  refill_reminder_event($groups['ALL'], $email, $text, $groups['MIN_DAYS']*24, 12);
}

//Called from Webform so that we didn't have to repeat conditional logic
function autopay_reminder_notice($groups) {

  $subject  = "Autopay Reminder.";
  $message  = "Because you are enrolled in autopay, Good Pill Pharmacy will be be billing your card ".implode(' <Pause />', str_split($groups['ALL'][0]['payment_card_last4'])).' for $'.$groups['ALL'][0]['payment_fee_default'].".00. Please let us right away if your card has recently changed. Again we will be billing your card for $".$groups['ALL'][0]['payment_fee_default'].".00 for last month's Order #".$groups['ALL'][0]['invoice_number']." of ".$groups['COUNT_FILLED']." items";

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

  $next_month = strtotime('first day of +1 months');
  $time_wait  = $next_month - time();

  autopay_reminder_event($groups['ALL'], $email, $text, $time_wait/60/60, 14);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function order_created_notice($groups) {

  $subject   = 'Good Pill is starting to prepare '.$groups['COUNT_FILLED'].' items for Order #'.$groups['ALL'][0]['invoice_number'].'.';
  $message   = 'If your address has recently changed please let us know right away.';
  $drug_list = '<br><br><u>These Rxs will be included once we confirm their availability:</u><br>';

  if ( ! $groups['ALL'][0]['refills_used']) {
    $message   .= ' Your first order will only be $6 total for all of your medications.';
    $drug_list .= implode(';<br>', array_merge($groups['FILLED_ACTION'], $groups['FILLED_NOACTION'])).';';
  } else {
    $drug_list .= implode(';<br>', $groups['FILLED_WITH_PRICES']).';';
  }

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
    ($groups['COUNT_FILLED'] >= $groups['COUNT_NOFILL']) ? 'Thanks for choosing Good Pill!' : 'Apologies for any inconvenience,',
    'The Good Pill Team',
    '',
    $suffix,
    '',
    ! $groups['COUNT_NOFILL'] ? '' : '<br><u>We are NOT filling these Rxs:</u><br>'.implode(';<br>', array_merge($groups['NOFILL_NOACTION'], $groups['NOFILL_ACTION'])).';',
    ''
  ]);

  //Remove Refill Reminders for new Rxs we just received Order #14512
  remove_drugs_from_refill_reminders($groups['ALL'][0]['first_name'], $groups['ALL'][0]['last_name'], $groups['ALL'][0]['birth_date'], $groups['FILLED']);

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
function order_hold_notice($groups, $missing_gsn = false) {

  if ($groups['COUNT_NOFILL']) {
    $subject = 'Good Pill is NOT filling your '.$groups['COUNT_NOFILL'].' items for Order #'.$groups['ALL'][0]['invoice_number'].'.';
    $message = '<u>We are NOT filling these Rxs:</u><br>'.implode(';<br>', array_merge($groups['NOFILL_NOACTION'], $groups['NOFILL_ACTION'])).';';
  } else {
    //If patients have no Rxs on their profile then this will be empty.
    $subject = 'Good Pill has not yet gotten your prescriptions.';
    $message = 'We are still waiting on your doctor or pharmacy to send us your prescriptions';
  }

  //[NULL, 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
  //Unsure but "Null" seems to come from SureScript Orders OR Hand Entered Orders (Fax, Pharmacy Transfer, Hard Copy, Phone)
  /*SELECT rx_source, MAX(gp_orders.invoice_number), COUNT(*) FROM `gp_orders` JOIN gp_order_items ON gp_order_items.invoice_number = gp_orders.invoice_number JOIN gp_rxs_single ON gp_rxs_single.rx_number = gp_order_items.rx_number WHERE order_source IS NULL GROUP BY rx_source ORDER BY `gp_orders`.`order_source` DESC*/
  $trigger = '';

  //Empty Rx Profile
  if ( ! $groups['COUNT_NOFILL']) {
    $trigger = 'We got your Order but';
  }
  //AUTOREFILL
  else if ($groups['ALL'][0]['order_source'] == 'Auto Refill v2') {
    $trigger = 'Your Rx is due for a refill but';
    log_error('order_hold_notice: Not filling Auto Refill?', $groups);
  }
  //TRANSFERS
  else if (in_array($groups['ALL'][0]['order_source'], ['Webform Transfer', 'Webform Transfer Note']))
    $trigger = 'We received your transfer request but';
  else if ($groups['ALL'][0]['order_source'] == NULL AND $groups['ALL'][0]['rx_source'] == 'Pharmacy')
    $trigger = 'We received your transfer but';

  //WEBFORM
  else if (in_array($groups['ALL'][0]['order_source'], ["Webform eRx", "Webform eRx Note"]))
    $trigger = 'You successfully registered but';
  else if (in_array($groups['ALL'][0]['order_source'], ["Webform Refill", "Webform Refill Note"]))
    $trigger = 'We received your refill request but';

  //DOCTOR
  else if ($groups['ALL'][0]['order_source'] == NULL AND in_array($groups['ALL'][0]['rx_source'], ["SureScripts", "Fax", "Phone"]))
    $trigger = 'We got Rxs from your doctor via '.$groups['ALL'][0]['rx_source'].' but';

  //HARDCOPY RX
  else if ($groups['ALL'][0]['order_source'] == NULL AND $groups['ALL'][0]['rx_source'] == 'Prescription')
    $trigger = 'We got your Rx in the mail '.$groups['ALL'][0]['rx_source'].' but';

  else
    log_error('order_hold_notice: unknown order/rx_source', $groups);

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

  $salesforce = ! $missing_gsn
    ? ''
    : [
      "subject" => "Order #".$groups['ALL'][0]['invoice_number']." ON HOLD because of missing GSN",
      "body" => "Please change drug(s) ".implode(', ', $groups['FILLED_NOACTION']+$groups['NOFILL_NOACTION'])." in Order #".$groups['ALL'][0]['invoice_number']. " to be ones that have a GSN number and/or add those GSNs to V2",
      "contact" => $groups['ALL'][0]['first_name'].' '.$groups['ALL'][0]['last_name'].' '.$groups['ALL'][0]['birth_date'],
      "assign_to" => ".Add/Remove Drug - RPh",
      "due_date" => date('Y-m-d')
    ];

  if ($missing_gsn)
    log_error('order_hold_notice: missing gsn', get_defined_vars());
  else
    log_notice('order_hold_notice: regular', get_defined_vars());

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  order_hold_event($groups['ALL'], $email, $text, $salesforce, 15/60);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function order_updated_notice($groups, $changed_fields) {

  //It's depressing to get updates if nothing is being filled.  So only send these if manually added and the order was just added (not just drugs changed)
  if ( ! $groups['COUNT_FILLED'] AND ! $groups['MANUALLY_ADDED']) {
    $cancel = cancel_events_by_person($groups['ALL'][0]['first_name'], $groups['ALL'][0]['last_name'], $groups['ALL'][0]['birth_date'], 'order_updated_notice', ['Order Created', 'Order Updated', 'Order Hold', 'No Rx', 'Needs Form']);
    return log_info('order_updated_notice NOT sent', get_defined_vars());
  }

  $subject = 'Good Pill update for Order #'.$groups['ALL'][0]['invoice_number'].($groups['COUNT_FILLED'] ? ' of '.$groups['COUNT_FILLED'].' items.' : '');
  $message = '';

  if ($groups['COUNT_FILLED'] AND ! $groups['ALL'][0]['refills_used']) {
    $message .= '<br><u>These Rxs will be included once we confirm their availability:</u><br>'.implode(';<br>', array_merge($groups['FILLED_ACTION'], $groups['FILLED_NOACTION'])).';';
  } else if ($groups['COUNT_FILLED']) {
    $message .= '<br><u>These Rxs will be included once we confirm their availability:</u><br>'.implode(';<br>', $groups['FILLED_WITH_PRICES']).';';
  }

  $suffix = implode('<br><br>', [
    "Note: if this is correct, there is no need to do anything. If you want to change or delay this order, please let us know as soon as possible. If delaying, please specify the date on which you want it filled, otherwise if you don't, we will delay it 3 weeks by default."
  ]);

  $email = [ "email" => DEBUG_EMAIL]; //$groups['ALL'][0]['email'] ];
  $text  = [ "sms" => DEBUG_PHONE, "message" => $subject.$message ]; //get_phones($groups['ALL'])

  $email['subject'] = $subject;
  $email['message'] = implode('<br>', [
    'Hello,',
    '',
    $subject.' We will notify you again once it ships.',
    $message,
    '',
    ($groups['COUNT_FILLED'] >= $groups['COUNT_NOFILL']) ? 'Thanks for choosing Good Pill!' : 'Apologies for any inconvenience,',
    'The Good Pill Team',
    '',
    $suffix,
    '',
    ! $groups['COUNT_NOFILL'] ? '' : '<br><u>We are NOT filling these Rxs:</u><br>'.implode(';<br>', array_merge($groups['NOFILL_NOACTION'], $groups['NOFILL_ACTION'])).';',
    '',
    json_encode($changed_fields),
    ''
  ]);

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  order_updated_event($groups['ALL'], $email, $text, 15/60);
}

function needs_form_notice($groups) {

  ///It's depressing to get updates if nothing is being filled
  if ($groups['FILLED']) {
    $subject = 'Welcome to Good Pill!  We are excited to fill your prescriptions.';
    $message = 'Your first order, #'.$groups['ALL'][0]['invoice_number'].", will cost $6, paid after you receive your medications. Please take 5mins to register so that we can fill the Rxs we got from your doctor as soon as possible. Once you register it will take 5-7 business days before you receive your order. You can register online at www.goodpill.org or by calling us at (888) 987-5187.<br><br><u>The drugs in your first order will be:</u><br>".implode(';<br>', $groups['FILLED_ACTION']).';';
    //log_error("NEEDS FORM NOTICE DOES NOT HAVE DRUGS LISTED", [$groups, $message, $subject]);
  }
  else {
    //log_error('NEEDS_FORM HOLD.  IS THIS EVER CALLED OR DOES IT GOTO ORDER_HOLD TEMPLATE', $groups);
    $subject = "Welcome to Good Pill. Unfortunately we can't complete your 1st Order";
    $message = "We are very sorry for the inconvenience but we can't fill the Rx(s) in Order #".$groups['ALL'][0]['invoice_number']." that we received from your doctor. Please ask your local pharmacy to contact us to get the prescription OR register online or over the phone and let us know to which pharmacy we should transfer the Rx(s).<br><br><u>The drugs we could not fill are:</u><br>".implode(';<br>', $groups['NOFILL_NOACTION']).";<br><br>Because we rely on donated medicine, we can only fill medications that are listed here www.goodpill.org/gp-stock";
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
    $hour_of_day   = [0, 17, 11, 17, 17, 17];

  } else {
    //B if after 5pm, the first one is 10am tomorrow, 5pm tomorrow, 10am the day after tomorrow, 5pm day after tomorrow.
    $hours_to_wait = [24, 24, 48, 48, 24*7, 24*14];
    $hour_of_day   = [11, 17, 11, 17, 17, 17];
  }

  log_notice("needs_form_notice is this right?", [$groups, $email]);

  $cancel = cancel_events_by_person($groups['ALL'][0]['first_name'], $groups['ALL'][0]['last_name'], $groups['ALL'][0]['birth_date'], 'needs_form_event', ['Needs Form']);

  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[0], $hour_of_day[0]);

  if ( ! $groups['COUNT_FILLED']) return; //Don't hassle folks if we aren't filling anything

  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[1], $hour_of_day[1]);
  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[2], $hour_of_day[2]);
  needs_form_event($groups['ALL'], $email, $text, $hours_to_wait[3], $hour_of_day[3]);
}

//We are coording patient communication via sms, calls, emails, & faxes
//by building commication arrays based on github.com/dscsa/communication-calendar
function no_rx_notice($deleted, $patient) {

  log_error('no_rx_notice. Does patient truly not have Rxs?', get_defined_vars());

  $subject = 'Good Pill received Order #'.$deleted['invoice_number'].' but is waiting for your prescriptions';
  $message  = ($deleted['order_source'] == 'Webform Transfer' OR $deleted['order_source'] == 'Transfer w/ Note')
    ? "We will attempt to transfer the Rxs you requested from your pharmacy."
    : "We haven't gotten any Rxs from your doctor yet but will notify you as soon as we do.";

  $email = [ "email" => $patient[0]['email'] ]; //TODO email is not actual a property on $deleted
  $text  = [ "sms"   => get_phones($patient), "message" => $subject.'. '.$message ];

  $email['subject'] = $subject;
  $email['message']  = implode('<br>', [
    'Hello,',
    '',
    $subject.'. '.$message,
    '',
    json_encode($deleted),
    '',
    'Thanks,',
    'The Good Pill Team',
    '',
    '',
    "Note: if this is correct, there is no need to do anything. If you think there is a mistake, please let us know as soon as possible."
  ]);

  //Wait 15 minutes to hopefully batch staggered surescripts and manual rx entry and cindy updates
  no_rx_event($deleted, $patient, $email, $text, 15/60);
}

//NOTE: UNLIKE OTHER COMM FUNCTIONS THIS TAKES DELETED AND NOT GROUPS
//THIS IS BECAUSE there is not an $order to make $groups
function order_canceled_notice($deleted, $patient) {

  $subject = "Good Pill canceled your Order #".$deleted['invoice_number'];

  $message = "Good Pill cancelled order $deleted[invoice_number]. We're sorry that we are unable to provide the reason for cancellation via text or email; if you believe this cancellation is an error, please give us a call at (888) 987-5187. Thank you.";

  $email = [ "email" => $patient[0]['email'], "raw" => json_encode($deleted) ]; //TODO email is not actual a property on $deleted
  $text  = [ "sms" => get_phones($patient),  "message" => $subject.'. '.$message ];

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


  log_notice("order_canceled_notice is this right?", [$patient, $deleted, $email]);
  //order_canceled_event($deleted, $patient, $email, $text, 15/60);
}

function confirm_shipment_notice($groups) {

    $days_ago = 5;

    $email = confirm_shipping_external($groups); //Existing customer just tell them it was delivered
    $salesforce = confirm_shipping_internal($groups, $days_ago+1); //New customer tell them it was delivered and followup with a call

    confirm_shipment_event($groups['ALL'], $email, $salesforce, $days_ago*24, 13);
}

function confirm_shipping_internal($groups, $days_ago) {

  if ((float) $groups['ALL'][0]['refills_used'] > 0) {
    return [];
  }

  ///It's depressing to get updates if nothing is being filled
  $subject  = "Follow up on new patient's first order";

  $salesforce = [
    "contact" => $groups['ALL'][0]['first_name'].' '.$groups['ALL'][0]['last_name'].' '.$groups['ALL'][0]['birth_date'],
    "assign_to" => ".Confirm Delivery - Tech",
    "due_date" => substr(get_start_time($days_ago*24), 0, 10),
    "subject" => $subject,
    "body" =>  implode('<br>', [
      'Hello,',
      '',
      $groups['ALL'][0]['first_name'].' '.$groups['ALL'][0]['last_name'].' '.$groups['ALL'][0]['birth_date'].' is a new patient.  They were shipped Order #'.$groups['ALL'][0]['invoice_number'].' with '.$groups['COUNT_FILLED'].' items '.$days_ago.' days ago.',
      '',
      'Please call them at '.$groups['ALL'][0]['phone1'].', '.$groups['ALL'][0]['phone2'].' and check on the following:',
      '- Order with tracking number '.tracking_link($groups['ALL'][0]['tracking_number']).' was delivered and that they received it',
      '',
      '- Make sure they got all '.$groups['COUNT_FILLED'].' of their medications, that we filled the correct number of pills, and answer any questions the patient has',
      $groups['COUNT_NOFILL'] ? '<br>- Explain why we did NOT fill:<br>'.implode(';<br>', array_merge($groups['NOFILL_NOACTION'], $groups['NOFILL_ACTION'])).'<br>' : '',
      '- Let them know they are currently set to pay via '.$groups['ALL'][0]['payment_method'].' and the cost of the '.$groups['COUNT_FILLED'].' items was $'.$groups['ALL'][0]['payment_fee_default'].' this time, but next time it will be $'.$groups['ALL'][0]['payment_total_default'],
      '',
      '- Review their current medication list and remind them which prescriptions we will be filling automatically and which ones they need to request 2 weeks in advance',
      '',
      'Thanks!',
      'The Good Pill Team',
      '',
      ''
    ])
  ];

  SirumLog::debug(
      'Attaching Newpatient Com Event',
      [
         "initial_rx_group" => $groups['ALL'][0]
      ]
  );

  return $salesforce;
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

  return $email;
}
