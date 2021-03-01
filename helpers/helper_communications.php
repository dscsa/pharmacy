<?php

require_once 'exports/export_gd_comm_calendar.php';

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

//All Communication should group drugs into 4 Categories based on ACTION/NOACTION and FILL/NOFILL
//1) FILLING NO ACTION
//2) FILLING ACTION
//3) NOT FILLING ACTION
//4) NOT FILLING NO ACTION
//TODO Much Better if this was a set of methods on an Order Object.  Like order->count_filled(filled = true/false), order->items_action(action = true/false), order->items_in_order(in_order = true/false), order->items_filled(filled = true/false, template = "{{name}} {{price}} {{item_message_keys}}")
function group_drugs($order, $mysql) {

  if ( ! $order) {
    log_error('GROUP_DRUGS did not get an order', get_defined_vars());
    return;
  }

  $groups = [
    "ALL" => [],
    "FILLED_ACTION" => [],
    "FILLED_NOACTION" => [],
    "ADDED_NOACTION" => [],
    "NOFILL_ACTION" => [],
    "NOFILL_NOACTION" => [],
    "FILLED" => [],
    "ADDED" => [],
    "FILLED_WITH_PRICES" => [],
    "ADDED_WITH_PRICES"  => [],
    "IN_ORDER" => [],
    "NO_REFILLS" => [],
    "NO_AUTOFILL" => [],
    "MIN_DAYS" => 366 //Max Days of a Script
  ];

  foreach ($order as $item) {

    $groups['ALL'][] = $item; //Want patient contact_info even if an emoty order

    if ( ! @$item['drug_name']) continue; //Might be an empty order

    $days = @$item['days_dispensed'];
    $fill = $days ? 'FILLED_' : 'NOFILL_';

    if (strpos($item['rx_message_key'], 'NO ACTION') !== false)
      $action = 'NOACTION';
    else if (strpos($item['rx_message_key'], 'ACTION') !== false)
      $action = 'ACTION';
    else
      $action = 'NOACTION';

    $price = patient_pricing_text($item);
    $msg   = patient_message_text($item);

    $groups[$fill.$action][] = $item['drug'].$msg;

    if (@$item['item_date_added'])
      $groups['IN_ORDER'][] = $item['drug'].$msg;

    if ($item['rx_number'] AND @$item['invoice_number']) { //Will be null if drug is NOT in the order.
      $sql = "
        UPDATE
          gp_order_items
        SET
          groups = CASE WHEN groups is NULL THEN '$fill$action' ELSE LEFT(concat('$fill$action < ', groups), 255) END
        WHERE
          invoice_number = $item[invoice_number] AND
          rx_number = $item[rx_number] AND
          (groups IS NULL OR groups NOT LIKE '$fill$action%')
      ";

      GPLog::debug(
        "Saving group into order_items",
        [
          "item"   => $item,
          "sql"    => $sql,
          "method" => "group_drugs"
        ]
      );

      $mysql->run($sql);
    }

    if ($days) {//This is handy because it is not appended with a message like the others
      $groups['FILLED'][] = $item['drug'];
      $groups['FILLED_WITH_PRICES'][] = $item['drug'].$price;
    }

    if ( ! @$item['refills_dispensed'] AND ! $item['rx_transfer'])
      $groups['NO_REFILLS'][] = $item['drug'].$msg;

    if ($days AND ! $item['rx_autofill'])
      $groups['NO_AUTOFILL'][] = $item['drug'].$msg;

    if ( ! @$item['refills_dispensed'] AND $days AND $days < $groups['MIN_DAYS'])
      $groups['MIN_DAYS'] = $days; //How many days before the first Rx to run out of refills

    $groups['MANUALLY_ADDED'] = is_added_manually($item);
  }

  $count_filled = count($groups['FILLED_ACTION']) + count($groups['FILLED_NOACTION']);
  $count_nofill = count($groups['NOFILL_ACTION']) + count($groups['NOFILL_NOACTION']);

  if (isset($order[0]['count_filled']) AND $count_filled != $order[0]['count_filled']) {
    log_error("group_drugs: wrong count_filled $count_filled != ".$order[0]['count_filled'], get_defined_vars());
  }

  if (isset($order[0]['count_nofill']) AND $count_nofill != $order[0]['count_nofill']) {
    log_error("group_drugs: wrong count_nofill $count_nofill != ".$order[0]['count_nofill'], get_defined_vars());
  }

  log_info('GROUP_DRUGS', get_defined_vars());

  return $groups;
}

//TODO consider making these methods so that they always stay upto
//TODO date and we don't have to recalcuate them when things change
function patient_message_text($item) {
  //item_message_text is set in set_item_invoice_data once dispensed
  $msg  = @$item['item_message_text'] ?: $item['rx_message_text'];
  $msg  = $msg ? ' '.str_replace(' **', '', $msg) : '';
  return $msg;
}

function patient_pricing_text($item) {
  if ( ! @$item['days_dispensed'] OR ! @$item['price_dispensed'])
    return '';

  return ", \${$item['price_dispensed']} for {$item['days_dispensed']} days";
}

function patient_drug_text($item) {
  return @$item['drug_generic'] ?: $item['drug_name'];
}

function patient_payment_method($item) {
  return @$item['payment_method_actual'] ?: $item['payment_method_default'];
}

function patient_days_dispensed($item) {
  return (float) (@$item['days_dispensed_actual'] ?: $item['days_dispensed_default']);
}

function patient_price_dispensed($item) {

  $price_per_month = $item['price_per_month'] ?: 0; //Might be null
  $price_dispensed = ceil($item['days_dispensed']*$price_per_month/30);

  if ($price_dispensed > 80)
    log_error("helper_full_fields: price too high, $$price_dispensed", get_defined_vars());

  return (float) $price_dispensed;
}

function patient_refills_dispensed($item) {
  /*
   * Create some variables with appropriate values
   */
  if ($item['refills_dispensed_actual'])
    return round($item['refills_dispensed_actual'], 2);

  if ($item['refills_dispensed_default'])
    return round($item['refills_dispensed_default'], 2);

  if ($item['refills_total'])
    return round($item['refills_total'], 2);
}

function patient_qty_dispensed($item) {
  return (float) (@$item['qty_dispensed_actual'] ?: $item['qty_dispensed_default']);
}

function send_created_order_communications($groups, $items_to_add) {

  if (is_webform_transfer($groups['ALL'][0]))
    return transfer_requested_notice($groups);

  if ( ! $groups['ALL'][0]['count_filled'] AND ! $groups['ALL'][0]['count_to_add']) {
    return log_error("send_created_order_communications: ! count_filled AND ! count_to_add. What to do?", $groups);
  }

  foreach ($items_to_add as $item) {

    $item['drug_name'] = patient_drug_text($item);
    $item['days_dispensed'] = (float) $item['days_to_add'];
    $item['price_dispensed'] = patient_price_dispensed($item);

    $groups['ADDED'][] = $item['drug_name']; //Equivalent of FILLED
    $groups['ADDED_WITH_PRICES'][] = $item['drug_name'].patient_pricing_text($item);  //Equivalent of FILLED_WITH_PRICES
    $groups['ADDED_NOACTION'][] = $item['drug_name'].$item['message_to_add']['EN']; //Equivalent of FILLED_NOACTION
  }

  log_error('send_created_order_communications', [
    'groups' => $groups,
    'items_to_add' => $items_to_add
  ]);

  order_created_notice($groups);
}

function send_shipped_order_communications($groups) {

  order_shipped_notice($groups);
  confirm_shipment_notice($groups);
  refill_reminder_notice($groups);
  //autopayReminderNotice(order, groups)

  if ($groups['ALL'][0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
    autopay_reminder_notice($groups);
}

function send_dispensed_order_communications($groups) {
  order_dispensed_notice($groups);
}

function send_updated_order_communications($groups, $added_deduped, $removed_deduped) {

  $patient_updates = [];

  if ($added_deduped) {
    $verb = count($added_deduped) == 1 ? 'was' : 'were';
    $patient_updates[] = implode(", ", $added_deduped)." $verb added to your order.";
  }

  if ($removed_deduped) {
    $verb = count($removed_deduped) == 1 ? 'was' : 'were';
    $patient_updates[] = implode(", ", $removed_deduped)." $verb removed from your order.";
  }

  log_error('send_updated_order_communications', [
    'groups'          => $groups,
    'added_deduped'   => $added_deduped,
    'removed_deduped' => $removed_deduped,
    'patient_updates' => $patient_updates
  ]);

  if ($patient_updates) //in case all were removed by the deduping process
    order_updated_notice($groups, $patient_updates);
}
