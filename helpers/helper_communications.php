<?php

require_once 'exports/export_gd_comm_calendar.php';

use Sirum\Logging\SirumLog;

//All Communication should group drugs into 4 Categories based on ACTION/NOACTION and FILL/NOFILL
//1) FILLING NO ACTION
//2) FILLING ACTION
//3) NOT FILLING ACTION
//4) NOT FILLING NO ACTION
function group_drugs($order, $mysql) {

  $groups = [
    "ALL" => [],
    "FILLED_ACTION" => [],
    "FILLED_NOACTION" => [],
    "NOFILL_ACTION" => [],
    "NOFILL_NOACTION" => [],
    "FILLED" => [],
    "FILLED_WITH_PRICES" => [],
    "NO_REFILLS" => [],
    "NO_AUTOFILL" => [],
    "MIN_DAYS" => 366 //Max Days of a Script
  ];

  foreach ($order as $item) {

    if ( ! $item['drug_name']) continue; //Might be an empty order

    $days = $item['days_dispensed'];
    $fill = $days ? 'FILLED_' : 'NOFILL_';
    $msg  = $item['rx_message_text'] ? ' '.str_replace(' **', '', $item['rx_message_text']) : '';

    if (strpos($item['rx_message_key'], 'NO ACTION') !== false)
      $action = 'NOACTION';
    else if (strpos($item['rx_message_key'], 'ACTION') !== false)
      $action = 'ACTION';
    else
      $action = 'NOACTION';

    $price = ($days AND $item['price_dispensed']) ? ', $'.((float) $item['price_dispensed']).' for '.$days.' days' : '';

    $groups['ALL'][] = $item;
    $groups[$fill.$action][] = $item['drug'].$msg;

    if ($item['rx_number']) { //Will be null if drug is NOT in the order.
      $sql = "
        UPDATE
          gp_order_items
        SET
          groups = CASE WHEN groups is NULL THEN '$fill$action' ELSE concat('$fill$action < ', groups) END
        WHERE
          invoice_number = $item[invoice_number] AND
          rx_number = $item[rx_number] AND
          (groups IS NULL OR groups NOT LIKE '$fill$action%')
      ";

      SirumLog::debug(
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

    if ( ! $item['refills_dispensed'] AND ! $item['rx_transfer'])
      $groups['NO_REFILLS'][] = $item['drug'].$msg;

    if ($days AND ! $item['rx_autofill'])
      $groups['NO_AUTOFILL'][] = $item['drug'].$msg;

    if ( ! $item['refills_dispensed'] AND $days AND $days < $groups['MIN_DAYS'])
      $groups['MIN_DAYS'] = $days; //How many days before the first Rx to run out of refills

    $groups['MANUALLY_ADDED'] = $item['item_added_by'] == 'MANUAL' OR $item['item_added_by'] == 'WEBFORM';
  }

  $groups['COUNT_FILLED'] = count($groups['FILLED_ACTION']) + count($groups['FILLED_NOACTION']);
  $groups['COUNT_NOFILL'] = count($groups['NOFILL_ACTION']) + count($groups['NOFILL_NOACTION']);

  if ($groups['COUNT_FILLED'] != $order[0]['count_filled']) {
    log_error('group_drugs: wrong count_filled', get_defined_vars());
  }

  if ($groups['COUNT_NOFILL'] != (count($order) - $order[0]['count_filled'])) {
    log_error('group_drugs: wrong count_nofill', get_defined_vars());
  }

  $sql = "
    UPDATE
      gp_orders
    SET
      count_filled = '$groups[COUNT_FILLED]',
      count_nofill = '$groups[COUNT_NOFILL]'
    WHERE
      invoice_number = {$order[0]['invoice_number']}
  ";

  if ($order[0]['invoice_number'])
    $mysql->run($sql);
  else
    log_error('GROUP_DRUGS has no invoice number', get_defined_vars());

  log_info('GROUP_DRUGS', get_defined_vars());

  return $groups;
}

function send_created_order_communications($groups) {

  if ( ! $groups['COUNT_NOFILL'] AND ! $groups['COUNT_FILLED']) {
    log_error("send_created_order_communications: ! count_nofill and ! count_filled. What to do?", $groups);
  }

  //['Not Specified', 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
  else if ($groups['ALL'][0]['order_source'] == 'Webform Transfer' OR $groups['ALL'][0]['order_source'] == 'Transfer /w Note')
    transfer_requested_notice($groups);

  else
    order_created_notice($groups);
}

function send_shipped_order_communications($groups) {

  order_shipped_notice($groups);
  confirm_shipment_notice($groups);
  refill_reminder_notice($groups);

  if ($groups['ALL'][0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
    autopay_reminder_notice($groups);
}

function send_dispensed_order_communications($groups) {
  order_dispensed_notice($groups);
}

function send_updated_order_communications($groups, $changed_fields) {
  order_updated_notice($groups, $changed_fields);
  log_info('order_updated_notice', get_defined_vars());
}
