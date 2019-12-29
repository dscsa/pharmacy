<?php

require_once 'exports/export_gd_comm_calendar.php';

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
    $msg  = $item['item_message_text'] ? ' '.$item['item_message_text'] : '';

    if (strpos($item['item_message_key'], 'NO ACTION') !== false)
      $action = 'NOACTION';
    else if (strpos($item['item_message_key'], 'ACTION') !== false)
      $action = 'ACTION';
    else
      $action = 'NOACTION';

    $price = $days ? ', $'.((float) $item['price_dispensed']).' for '.$days.' days' : '';

    $groups['ALL'][] = $item;
    $groups[$fill.$action][] = $item['drug'].$msg;

    if ($item['rx_number']) { //Will be null drug is NOT in the order. "Group" is keyword so must have ``
      $sql = "
        UPDATE
          gp_order_items
        SET
         `group` = CASE WHEN `group` is NULL THEN '$fill$action' ELSE concat('$fill$action < ', `group`) END
        WHERE
          invoice_number = $item[invoice_number] AND
          rx_number = $item[rx_number] AND
          `group` != '$fill$action'
      ";

      $mysql->run($sql);
    }

    if ($days) {//This is handy because it is not appended with a message like the others
      $groups['FILLED'][] = $item['drug'];
      $groups['FILLED_WITH_PRICES'][] = $item['drug'].$price;
    }

    if ( ! $item['refills_total'])
      $groups['NO_REFILLS'][] = $item['drug'].$msg;

    if ($days AND ! $item['rx_autofill'])
      $groups['NO_AUTOFILL'][] = $item['drug'].$msg;

    if ( ! $item['refills_total'] AND $days AND $days < $groups['MIN_DAYS'])
      $groups['MIN_DAYS'] = $days;

    $groups['MANUALLY_ADDED'] = $item['item_added_by'] == 'MANUAL' OR $item['item_added_by'] == 'WEBFORM';
  }

  $groups['COUNT_FILLED'] = count($groups['FILLED_ACTION']) + count($groups['FILLED_NOACTION']);
  $groups['COUNT_NOFILL'] = count($groups['NOFILL_ACTION']) + count($groups['NOFILL_NOACTION']);

  $sql = "
    UPDATE
      gp_orders
    SET
      count_filled = '$groups[COUNT_FILLED]',
      count_nofill = '$groups[COUNT_NOFILL]'
    WHERE
      invoice_number = {$order[0]['invoice_number']}
  ";

  $mysql->run($sql);

  log_info('GROUP_DRUGS', get_defined_vars());

  return $groups;
}

function send_created_order_communications($groups) {

  if ( ! $groups['ALL'][0]['pharmacy_name']) //Use Pharmacy name rather than $New to keep us from repinging folks if the row has been readded
    needs_form_notice($groups);

  else if ( ! $groups['COUNT_NOFILL'] AND ! $groups['COUNT_FILLED'])
    no_rx_notice($groups);

  else if ( ! $groups['COUNT_FILLED'])
    order_hold_notice($groups);

  //['Not Specified', 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
  else if ($groups['ALL'][0]['order_source'] == 'Webform Transfer' OR $groups['ALL'][0]['order_source'] == 'Transfer /w Note')
    transfer_requested_notice($groups);

  else
    order_created_notice($groups);
}

function send_deleted_order_communications($order) {

  //TODO We need something here!
  order_canceled_notice($order);
  log_info('Order was deleted', get_defined_vars());
}

function send_shipped_order_communications($groups) {

  order_shipped_notice($groups);
  confirm_shipment_notice($groups);
  refill_reminder_notice($groups);
  unpend_order($groups['ALL']);

  if ($groups['ALL'][0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
    autopay_reminder_notice($groups);
}

function send_dispensed_order_communications($groups) {
  order_dispensed_notice($groups);
}

function send_updated_order_communications($groups) {
  order_updated_notice($groups);
  log_info('order_updated_notice', get_defined_vars());
}
