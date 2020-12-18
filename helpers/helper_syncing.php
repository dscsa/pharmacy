<?php

require_once 'exports/export_gd_transfer_fax.php';

use Sirum\Logging\SirumLog;

//Remove Only flag so that once we communicate what's in an order to a patient we "lock" it, so that if new SureScript come in we remove them so we don't surprise a patient with new drugs
function sync_to_order($order, $updated = null) {

  $notices         = [];
  $items_to_sync   = [];
  $items_to_add    = [];
  $items_to_remove = [];
  $new_count_items = $order[0]['count_items'];

  foreach($order as $item) {

    if ($item['rx_dispensed_id']) {
      log_info('syncing item canceled because already dispensed', $item);
      continue;
    }

    if ( ! $item['item_date_added'] AND $item['rx_message_key'] == 'NO ACTION PAST DUE AND SYNC TO ORDER') { //item_date_added because once we add it don't keep readding it

      if ($updated) {
        $notices[] = ["sync_to_order adding item: updated so did not add 'NO ACTION PAST DUE AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", $item];
        continue;
      }

      $new_count_items++;
      $items_to_sync[] = ['ADD', 'NO ACTION PAST DUE AND SYNC TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];
      //log_notice('sync_to_order adding item: PAST DUE AND SYNC TO ORDER', "$item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ( ! $item['item_date_added'] AND $item['rx_message_key'] == 'NO ACTION NO NEXT AND SYNC TO ORDER') { //item_date_added because once we add it don't keep readding it

      if ($updated) {
        $notices[] = ["sync_to_order adding item: updated so did not add 'NO ACTION NO NEXT AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", $item];
        continue;
      }

      $new_count_items++;
      $items_to_sync[] = ['ADD', 'NO ACTION NO NEXT AND SYNC TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];
      //log_notice('sync_to_order adding item: PAST DUE AND SYNC TO ORDER', "$item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ( ! $item['item_date_added'] AND $item['rx_message_key'] == 'NO ACTION DUE SOON AND SYNC TO ORDER') { //item_date_added because once we add it don't keep readding it

      if ($updated) {
        $notices[] = ["sync_to_order adding item: updated so did not add 'NO ACTION DUE SOON AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", $item];
        continue;
      }

      $new_count_items++;
      $items_to_sync[] = ['ADD', 'NO ACTION DUE SOON AND SYNC TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];
      //log_notice('sync_to_order adding item: DUE SOON AND SYNC TO ORDER', "$item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ( ! $item['item_date_added'] AND $item['rx_message_key'] == 'NO ACTION NEW RX SYNCED TO ORDER') { //item_date_added because once we add it don't keep readding it

      if ($updated) {
        $notices[] = ["sync_to_order adding item: updated so did not add 'NO ACTION NEW RX SYNCED TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", $item];
        continue;
      }

      $new_count_items++;
      $items_to_sync[] = ['ADD', 'NO ACTION NEW RX SYNCED TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];

      continue;
    }

    //Don't remove items with a missing GSN as this is something we need to do
    if ($item['item_date_added'] AND ! $item['days_dispensed'] AND $item['drug_gsns']) {

      if ($updated OR is_added_manually($item)) {
        $notices[] = ['aborting helper_syncing because updated OR item to be REMOVED was added MANUALLY', $item];
        continue;
      }

      $new_count_items--;
      $items_to_sync[]   = ['REMOVE', $item['rx_message_key'], $item];
      $items_to_remove[] = $item['rx_number'];
      //log_notice('sync_to_order removing item', "$item[invoice_number] $item[rx_number] $item[drug], $item[stock_level], $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ($item['item_date_added'] AND $item['rx_number'] != $item['best_rx_number']) {

      if ($updated OR is_added_manually($item)) {
        $notices[] = ['aborting helper_syncing because updated OR item to be SWITCHED was added MANUALLY', $item];
        continue;
      }

      $items_to_sync[]   = ['SWITCH', 'RX_NUMBER != BEST_RX_NUMBER', $item];
      $items_to_add[]    = $item['best_rx_number'];
      $items_to_remove[] = $item['rx_number'];
      //log_notice('sync_to_order switching items', "$item[invoice_number] $item[drug] $item[rx_message_key] $item[rx_number] -> $item[best_rx_number]");

      continue;
    }
  }

  if ($notices)
    log_notice("helper_syncing: notices", $notices);

  if ($updated AND $notices) {

    $salesforce   = [
      "subject"   => "Ignoring changes to an existing order ".$order[0]['invoice_number'],
      "body"      => implode(',', $notices),
      "contact"   => $order[0]['first_name'].' '.$order[0]['last_name'].' '.$order[0]['birth_date'],
      "assign_to" => ".Add/Remove Drug - RPh",
      "due_date"  => date('Y-m-d')
    ];

    $event_title = "$salesforce[subject] $salesforce[due_date]";

    create_event($event_title, [$salesforce]);
  }

  if ($items_to_remove) {

    SirumLog::notice(
      "helper_syncing: items_to_remove (export_cp_remove_items)",
      [
        'new_count_items' => $new_count_items,
        'items_to_remove' => $items_to_remove,
        'items_to_sync' => $items_to_sync,
        'item' => $item,
        'order' => $order
      ]
    );

    export_cp_remove_items($item['invoice_number'], $items_to_remove);
  }

  if ($items_to_add) {

    SirumLog::notice(
      "helper_syncing: items_to_add (export_cp_add_items)",
      [
        'new_count_items' => $new_count_items,
        'items_to_add' => $items_to_add,
        'items_to_sync' => $items_to_sync,
        'item' => $item,
        'order' => $order
      ]
    );

    export_cp_add_items($item['invoice_number'], $items_to_add);
  }

  return ['new_count_items' => $new_count_items, 'items_to_sync' => $items_to_sync];
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

  if ($new_days_default == DAYS_STD)
    return $order;

  foreach ($order as $item) {

    //Don't try to sync stuff not in order, just what's in the order
    if ( ! $item['item_date_added'])
      continue;

    $sync_to_date_days_change = $new_days_default - $item['days_dispensed_default'];

    //Don't label something as synced if there is no change
    if ( ! $sync_to_date_days_change)
      continue;

    $order[$i]['days_dispensed']  = $order[$i]['days_dispensed_default']  = $new_days_default;
    $order[$i]['qty_dispensed']   = $order[$i]['qty_dispensed_default']   = $new_days_default*$item['sig_qty_per_day'];
    $order[$i]['price_dispensed'] = $order[$i]['price_dispensed_default'] = ceil($days*($item['price_per_month'] ?: 0)/30); //Might be null

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
        price_dispensed_default           = ".$order[$i]['price_dispensed_default']."
        sync_to_date_days_before          = $item[days_dispensed_default],
        sync_to_date_max_days_default     = $max_days_default,
        sync_to_date_max_days_default_rxs = '".implode(',', $max_days_default_rxs)."',
        sync_to_date_min_days_refills     = $min_days_refills,
        sync_to_date_min_days_refills_rxs = '".implode(',', $min_days_refills_rxs)."',
        sync_to_date_min_days_stock       = $min_days_stock,
        sync_to_date_min_days_stock_rxs   = '".implode(',', $min_days_stock_rxs)."'
      WHERE
        rx_number = $item[rx_number]
        AND invoice_number => ".$order[0]['invoice_number'];

    $mysql->run($sql);

    v2_unpend_item($order[$i], $mysql);
    v2_pend_item($order[$i], $mysql);

    $order[$i] = export_cp_set_rx_message($order[$i], RX_MESSAGE['NO ACTION SYNC TO DATE'], $mysql);

    log_notice('helper_syncing: sync_to_date and repended in v2', ['item' => $order[$i], 'sql' => $sql]);
  }

  return $order;
}
