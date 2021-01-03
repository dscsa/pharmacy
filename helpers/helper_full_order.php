<?php
//order created -> add any additional rxs to order -> import order items -> sync all drugs in order
require_once 'exports/export_cp_rxs.php';
require_once 'helpers/helper_full_fields.php';

use Sirum\Logging\SirumLog;

function load_full_order($partial, $mysql, $overwrite_rx_messages = false) {

  if ( ! isset($partial['invoice_number'])) {
    log_error('ERROR! get_full_order: was not given an invoice number', $partial);
    return;
  }

  $month_interval = 6;
  $where = "
    AND (CASE WHEN refills_total OR item_date_added THEN gp_rxs_grouped.rx_date_expired ELSE COALESCE(gp_rxs_grouped.rx_date_transferred, gp_rxs_grouped.refill_date_last) END) > CURDATE() - INTERVAL $month_interval MONTH
  ";

  //gp_orders.invoice_number and other fields at end because otherwise potentially null gp_order_items.invoice_number will override gp_orders.invoice_number
  //Don't fully understand LEFT vs RIGHT Joins but experimented around on a missing full_order that had 0 results (#33374) until I got the max number of rows
  $sql = "
    SELECT
      *,
      gp_orders.invoice_number,
      gp_order_items.invoice_number as dontuse_item_invoice,
      gp_orders.invoice_number as dontuse_order_invoice,
      gp_rxs_grouped.*, -- Need to put this first based on how we are joining, but make sure these grouped fields overwrite their single equivalents
      1 as is_order,
      0 as is_patient,
      0 as is_item
    FROM
      gp_orders
    LEFT JOIN gp_patients ON
      gp_patients.patient_id_cp = gp_orders.patient_id_cp
    LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
      gp_rxs_grouped.patient_id_cp = gp_orders.patient_id_cp
    LEFT JOIN gp_order_items ON
      gp_order_items.invoice_number = gp_orders.invoice_number AND rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%') -- In case the rx is added in a different orders
    LEFT JOIN gp_rxs_single ON -- Needed to know qty_left for sync-to-date
      COALESCE(gp_order_items.rx_number, gp_rxs_grouped.best_rx_number) = gp_rxs_single.rx_number
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic -- this is for the helper_days_and_message msgs for unordered drugs
    WHERE
      gp_orders.invoice_number = $partial[invoice_number]
  ";

  $order = $mysql->run($sql.$where)[0];

  if ( ! $order OR ! $order[0]['invoice_number']) {
    log_error("ERROR! get_full_order: no order with invoice number:$partial[invoice_number] #1 of 2. Order Was Temp Deleted to Add/Remove Items? No Recent Rxs?", get_defined_vars());

    $order = $mysql->run($sql)[0];

    if ( ! $order OR ! $order[0]['invoice_number']) {
      log_error("ERROR! get_full_order: no order with invoice number:$partial[invoice_number] #2 of 2. No Rxs? Patient just registered (invoice number is set and used in query but no items so order not imported from CP)?", get_defined_vars());
      return;
    }
  }

  $order = add_full_fields($order, $mysql, $overwrite_rx_messages);
  usort($order, 'sort_order_by_day'); //Put Rxs in order (with Rx_Source) at the top
  $order = add_wc_status_to_order($order);

  return $order;
}

function add_wc_status_to_order($order) {

  $old_order_stage_wc = $order[0]['order_stage_wc'];
  $new_order_stage_wc = get_order_stage_wc($order);

  $drug_names     = []; //Append qty_per_day if multiple of same strength, do this after sorting

  foreach($order as $i => $item) {
    $order[$i]['order_stage_wc'] = $new_order_stage_wc;

    if (isset($drug_names[$item['drug_name']])) {
      $order[$i]['drug_name'] .= ' ('.( (float) $item['sig_qty_per_day'] ).' per day)';
      //log_notice("helper_full_order add_wc_status_to_order: appended sig_qty_per_day to duplicate drug ".$item['drug']." >>> ".$drug_names[$item['drug']], [$order, $item, $drug_names]);
    } else {
      $drug_names[$item['drug_name']] = $item['sig_qty_per_day'];
    }
  }

  if ($old_order_stage_wc != $new_order_stage_wc) {
    SirumLog::debug(
        "helper_full_order: add_wc_status_to_order. change in status",
        [
          'old_status_wc'  => $old_order_stage_wc,
          'new_status_wc'  => $new_order_stage_wc,
          'invoice_number' => $order[0]['invoice_number']
        ]
    );
    //export_wc_update_order_metadata($order);
  }

  return $order;
}

function sort_order_by_day($a, $b) {
  if ($b['days_dispensed'] > 0 AND $a['days_dispensed'] == 0) return 1;
  if ($a['days_dispensed'] > 0 AND $b['days_dispensed'] == 0) return -1;
  if ($b['item_date_added'] > 0 AND $a['item_date_added'] == 0) return 1;
  if ($a['item_date_added'] > 0 AND $b['item_date_added'] == 0) return -1;
  return strcmp($a['rx_message_text'].$a['drug'], $b['rx_message_text'].$b['drug']);
}

/*
const ORDER_STATUS_WC = [

  'late-mail-pay'              => 'Shipped (Mail Payment Not Made)',
  'late-auto-pay-card-missing' => 'Shipped (Autopay Card Missing)',
  'late-auto-pay-card-expired' => 'Shipped (Autopay Card Expired)',
  'late-auto-pay-card-failed'  => 'Shipped (Autopay Card Failed)',
  'late-online-pay'            => 'Shipped (Online Payment Not Made)',
  'late-plan-approved'         => 'Shipped (Payment Plan Approved)',

  'completed-card-pay'    => 'Completed (Paid by Card)',
  'completed-mail-pay'    => 'Completed (Paid by Mail)',
  'completed-finaid'      => 'Completed (Financial Aid)',
  'completed-fee-waived'  => 'Completed (Fee Waived)',
  'completed-clinic-pay'  => 'Completed (Paid by Clinic)',
  'completed-auto-pay'    => 'Completed (Paid by Autopay)',
  'completed-refused-pay' => 'Completed (Refused to Pay)',

  'returned-usps'         => 'Returned (USPS)',
  'returned-customer'     => 'Returned (Customer)'
];
*/
function get_order_stage_wc($order) {

  $count_filled = in_array($order[0]['order_stage_cp'], ['Shipped', 'Dispensed'])
    ? $order[0]['count_items']
    : $order[0]['count_filled'];

  //Anything past shipped we just have to rely on WC
  if ($order[0]['order_stage_wc'] AND preg_match('/shipped|late|done|return/i', $order[0]['order_stage_wc'])) {

    if ( ! $count_filled AND ! $order[0]['tracking_number'] AND ! $order[0]['payment_method_actual'])
      log_error('helper_full_order: get_order_stage_wc error', $order);

    return str_replace('wc-', '', $order[0]['order_stage_wc']);
  }


  if ($order[0]['order_stage_wc'] == 'wc-processing')
    log_error('Problem: get_order_stage_wc wc-processing', $order[0]);

  /*
  'confirm-*' means no drugs in the order yet so check for count_items
  order_source: NULL, O Refills, Auto Refill v2, Webform eRX, Webform eRX Note, Webform Refill, Webform Refill Note, Webform Transfer, Webform Transfer Note
  */

  if ( ! $count_filled AND $order[0]['rx_message_key'] != 'ACTION NEEDS FORM')
    log_notice('get_order_stage_wc: double check count_filled == 0', [
      'invoice_number' => $order[0]['invoice_number'],
      'order_stage_cp' => $order[0]['order_stage_cp'],
      'order_stage_wc' => $order[0]['order_stage_wc'],
      'rx_message_key' => $order[0]['rx_message_key'],
      'tracking_number' => $order[0]['tracking_number'],
      'order' => $order
    ]);

  if ( ! $count_filled AND ! $order[0]['order_source'])
    return 'confirm-new-rx'; //New SureScript(s) that we are not filling

  if ( ! $count_filled AND in_array($order[0]['order_source'], ['Webform eRX', 'Webform eRX Note']))
    return 'confirm-new-rx'; //New SureScript(s) that we are not filling

  if ( ! $count_filled AND in_array($order[0]['order_source'], ['Webform Transfer', 'Webform Transfer Note']))
    return 'confirm-transfer';

  if ( ! $count_filled AND in_array($order[0]['order_source'], ['Webform Refill', 'Webform Refill Note']))
    return 'confirm-refill';

  if ( ! $count_filled AND in_array($order[0]['order_source'], ['Auto Refill v2', 'O Refills']))
    return 'confirm-autofill';

  if ( ! $count_filled) {
    log_error('get_order_stage_wc error: confirm-* unknown order_source', get_defined_vars());
    return 'on-hold';
  }

  /*
  'prepare-*' means drugs are in the order but order is not yet shipped
  rx_source: Fax, Pharmacy, Phone, Prescription, SureScripts
  */

  $elapsed_time = time() - strtotime($order[0]['order_date_added']);

  if ( ! $order[0]['order_date_dispensed'] AND $elapsed_time > 7*24*60*60) {
    email(SUPPORT_EMAIL, "Order is late. Follow up with patient?", $order);
    log_warning('helper_full_order: order is '.floor($elapsed_time/60/60/24).' days old', $order[0]);
  }

  if ( ! $order[0]['tracking_number'] AND in_array($order[0]['order_source'], ['Webform Refill', 'Webform Refill Note', 'Auto Refill v2', 'O Refills']))
    return 'prepare-refill';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'SureScripts')
    return 'prepare-erx';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Fax')
    return 'prepare-fax';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Pharmacy')
    return 'prepare-transfer';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Phone')
    return 'prepare-phone';

  if ( ! $order[0]['tracking_number'] AND $order[0]['rx_source'] == 'Prescription')
    return 'prepare-mail';

  if ( ! $order[0]['tracking_number']) {
    log_error('get_order_stage_wc error: prepare-* unknown rx_source', get_defined_vars());
    return 'on-hold';
  }

  /*

    const PAYMENT_METHOD = [
      'COUPON'       => 'coupon',
      'MAIL'         => 'cheque',
      'ONLINE'       => 'cash',
      'AUTOPAY'      => 'stripe',
      'CARD EXPIRED' => 'stripe-card-expired'
    ];

    'shipped-*' means order was shipped but not yet paid.  We have to go by order_stage_wc here
    rx_source: Fax, Pharmacy, Phone, Prescription, SureScripts
  */

  if ($order[0]['order_stage_wc'] == 'shipped-partial-pay')
    return 'shipped-part-pay';

  if ($order[0]['payment_method'] == PAYMENT_METHOD['MAIL'])
    return 'shipped-mail-pay';

  if ($order[0]['payment_method'] == PAYMENT_METHOD['AUTOPAY'])
    return 'shipped-auto-pay';

  if ($order[0]['payment_method'] == PAYMENT_METHOD['ONLINE'])
    return 'shipped-web-pay';

  if ($order[0]['payment_method'] == PAYMENT_METHOD['COUPON'])
    return 'done-coupon-pay';

  if ($order[0]['payment_method'] == PAYMENT_METHOD['CARD EXPIRED'])
    return 'late-card-expired';

  log_error('get_order_stage_wc error: shipped-* unknown payment_method', get_defined_vars());

  // Strip on the wc- so we don't get duplicates
  return str_replace('wc-', '', $order[0]['order_stage_wc']);
}

function get_current_orders($mysql, $conditions = []) {

  $where = "";
  foreach ($conditions as $key => $val) {
    $where .= "$key = $val AND\n";
  }

  $sql = "
    SELECT
      *
    FROM
      gp_orders
    WHERE
      $where
      order_date_dispensed IS NULL
  ";

  return $mysql->run($sql)[0];
}
