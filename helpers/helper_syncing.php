<?php

require_once 'exports/export_gd_transfer_fax.php';

use Sirum\Logging\SirumLog;

function sync_to_order_new_rx($item, $patient_or_order) {

  if (@$item['item_date_added']) return false;  //Cannot sync if already in order!

  $not_offered  = is_not_offered($item);
  $refill_only  = is_refill_only($item);
  $has_refills  = ($item['refills_total'] > NO_REFILL);
  $eligible     = ($has_refills AND $item['rx_autofill'] AND ! $not_offered AND ! $refill_only AND ! $item['refill_date_next']);

  SirumLog::debug(
      "sync_to_order_new_rx: $item[invoice_number] $item[drug_generic] ".($eligible ? 'Syncing' : 'Not Syncing'),
      [
          'invoice_number' => $patient_or_order[0]['invoice_number'],
          'vars' => get_defined_vars()
      ]
  );

  return $eligible;
}

function sync_to_order_past_due($item, $patient_or_order) {

  if (@$item['item_date_added']) return false;  //Cannot sync if already in order!

  $has_refills  = ($item['refills_total'] > NO_REFILL);

  $eligible = ($has_refills AND $item['refill_date_next'] AND (strtotime($item['refill_date_next']) - strtotime($item['order_date_added'])) < 0);

  SirumLog::debug(
    "sync_to_order_past_due: $item[invoice_number] $item[drug_generic] ".($eligible ? 'Syncing' : 'Not Syncing'),
    [
      'invoice_number' => $patient_or_order[0]['invoice_number'],
      'vars' => get_defined_vars()
    ]
  );

  return $eligible;
}

//Order 29017 had a refill_date_first and rx/pat_autofill ON but was missing a refill_date_default/refill_date_manual/refill_date_next
function sync_to_order_no_next($item, $patient_or_order) {

  if (@$item['item_date_added']) return false;  //Cannot sync if already in order!

  $has_refills  = ($item['refills_total'] > NO_REFILL);
  $is_refill  = $item['refill_date_first']; //Unlink others don't use is_refill (which checks all matching drugs / ignoring sig_qty_per day differences).  This might be an Rx the pharmacists are intentionally not activating.  See the 2x "Bumetanide 1mg" in Order 52129

  $eligible = ($has_refills AND $is_refill AND ! $item['refill_date_next']);

  SirumLog::debug(
      "sync_to_order_no_next: $item[invoice_number] $item[drug_generic] ".($eligible ? 'Syncing' : 'Not Syncing'),
      [
          'invoice_number' => $patient_or_order[0]['invoice_number'],
          'vars' => get_defined_vars()
      ]
  );

  return $eligible;
}

function sync_to_order_due_soon($item, $patient_or_order) {

  if (@$item['item_date_added']) return false;  //Cannot sync if already in order!

  $has_refills  = ($item['refills_total'] > NO_REFILL);

  $eligible = ($has_refills AND $item['refill_date_next'] AND (strtotime($item['refill_date_next'])  - strtotime($item['order_date_added'])) <= DAYS_EARLY*24*60*60);

  SirumLog::debug(
      "sync_to_order_due_soon: $item[invoice_number] $item[drug_generic] ".($eligible ? 'Syncing' : 'Not Syncing'),
      [
          'invoice_number' => $patient_or_order[0]['invoice_number'],
          'vars' => get_defined_vars()
      ]
  );

  return $eligible;
}

function sync_to_date($order, $mysql) {

  //pick the max day of the Rxs in the order that can be filled by every rx in the order
  $max_days_default = 0;
  $min_days_refills = DAYS_MAX;
  $min_days_stock   = DAYS_MAX;

  $max_days_default_rxs = [];
  $min_days_refills_rxs = [];
  $min_days_stock_rxs   = [];

  foreach ($order as $item) {

    //Abort if any item in the order is already dispensed
    if ($item['rx_dispensed_id']) {
      log_notice("sync_to_date: not syncing, already dispensed", ['item' => $item]);
      return $order;
    }

    //Don't try to sync stuff not in order, just what's in the order
    if ( ! $item['item_date_added'])
      continue;

    if ($item['days_dispensed_default'] >= $max_days_default AND $item['days_dispensed_default'] <= DAYS_MAX) {
      $max_days_default       = $item['days_dispensed_default'];
      $max_days_default_rxs[] = $item['rx_number'];
    }

    $days_left_in_refills = days_left_in_refills($item);
    if ($days_left_in_refills <= $min_days_refills AND $days_left_in_refills >= DAYS_MIN) {
      $min_days_refills        = $days_left_in_refills;
      $min_days_refills_rxs[]  = $item['rx_number'];
    }

    $days_left_in_stock = days_left_in_stock($item);
    if ($days_left_in_stock <= $min_days_stock AND $days_left_in_stock >= DAYS_MIN) {
      $min_days_stock        = $days_left_in_stock;
      $min_days_stock_rxs[]  = $item['rx_number'];
    }
  }

  $new_days_default = min($max_days_default, $min_days_refills, $min_days_stock);

  log_notice($new_days_default == DAYS_STD ? "sync_to_date: not syncing, days_std" : "sync_to_date: syncing", [
    'invoice_number'       => $order[0]['invoice_number'],
    'new_days_default'     => $new_days_default,
    'max_days_default'     => $max_days_default,
    'min_days_refills'     => $min_days_refills,
    'min_days_stock'       => $min_days_stock,
    'max_days_default_rxs' => $max_days_default_rxs,
    'min_days_refills_rxs' => $min_days_refills_rxs,
    'min_days_stock_rxs'   => $min_days_stock_rxs,
    'order'                => $order
  ]);

  if ( ! $new_days_default OR $new_days_default == DAYS_STD)
    return $order;

  foreach ($order as $i => $item) {

    //Don't try to sync stuff not in order, just what's in the order
    if ( ! $item['item_date_added'])
      continue;

    $sync_to_date_days_change = $new_days_default - $item['days_dispensed_default'];

    //Don't set rx_message to something as being synced if it was the target and therefore didn't change
    if ($sync_to_date_days_change > -5 AND $sync_to_date_days_change < 5)
      continue;

    $order[$i]['qty_dispensed']   = $order[$i]['qty_dispensed_default']   = $new_days_default*$item['sig_qty_per_day'];
    $order[$i]['price_dispensed'] = $order[$i]['price_dispensed_default'] = ceil($new_days_default*($item['price_per_month'] ?: 0)/30); //Might be null

    //NOT CURRENTLY USED BUT FOR AUDITING PURPOSES
    $order[$i]['sync_to_date_days_before']          = $item['days_dispensed_default'];
    $order[$i]['sync_to_date_days_change']          = $sync_to_date_days_change;

    $order[$i]['sync_to_date_max_days_default']     = $max_days_default;
    $order[$i]['sync_to_date_max_days_default_rxs'] = implode(',', $max_days_default_rxs);

    $order[$i]['sync_to_date_min_days_refills']     = $min_days_refills;
    $order[$i]['sync_to_date_min_days_refills_rxs'] = implode(',', $min_days_refills_rxs);

    $order[$i]['sync_to_date_min_days_stock']       = $min_days_stock;
    $order[$i]['sync_to_date_min_days_stock_rxs']   = implode(',', $min_days_stock_rxs);

    $sql = "
      UPDATE
        gp_order_items
      SET
        days_dispensed_default            = $new_days_default,
        qty_dispensed_default             = ".$order[$i]['qty_dispensed_default'].",
        price_dispensed_default           = ".$order[$i]['price_dispensed_default'].",
        sync_to_date_days_before          = $item[days_dispensed_default],
        sync_to_date_max_days_default     = $max_days_default,
        sync_to_date_max_days_default_rxs = '".implode(',', $max_days_default_rxs)."',
        sync_to_date_min_days_refills     = $min_days_refills,
        sync_to_date_min_days_refills_rxs = '".implode(',', $min_days_refills_rxs)."',
        sync_to_date_min_days_stock       = $min_days_stock,
        sync_to_date_min_days_stock_rxs   = '".implode(',', $min_days_stock_rxs)."'
      WHERE
        rx_number = $item[rx_number]
        AND invoice_number = ".$order[0]['invoice_number'];

    $mysql->run($sql);

    $order[$i] = v2_unpend_item($item, $mysql, "unpend for sync_to_date");
    $order[$i] = v2_pend_item($item, $mysql,  "pend for sync_to_date");

    $order[$i]['days_dispensed'] = $order[$i]['days_dispensed_default']  = $new_days_default;
    $order[$i] = export_cp_set_rx_message($item, RX_MESSAGE['NO ACTION SYNC TO DATE'], $mysql);

    log_notice('helper_syncing: sync_to_date and repended in v2', ['item' => $item, 'sql' => $sql]);
  }

  return $order;
}
