<?php

require_once 'exports/export_gd_transfer_fax.php';

//Remove Only flag so that once we communicate what's in an order to a patient we "lock" it, so that if new SureScript come in we remove them so we don't surprise a patient with new drugs
function sync_to_order($order, $updated = null) {

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
        log_notice("sync_to_order adding item: updated so did not add 'NO ACTION PAST DUE AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);
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
        log_notice("sync_to_order adding item: updated so did not add 'NO ACTION NO NEXT AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);
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
        log_notice("sync_to_order adding item: updated so did not add 'NO ACTION DUE SOON AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);
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
        if ($item['drug_gsns'])
          log_notice("sync_to_order adding item: updated so did not add 'NO ACTION NEW RX SYNCED TO ORDER' $item[invoice_number] $item[drug] $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);

        continue;
      }

      $new_count_items++;
      $items_to_sync[] = ['ADD', 'NO ACTION NEW RX SYNCED TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];

      continue;
    }

    //Don't remove items with a missing GSN as this is something we need to do
    if ($item['item_date_added'] AND ! $item['days_dispensed'] AND $item['drug_gsns']) {

      //DEBUG CODE SHOULD NOT BE NEEDED
      if ($item['rx_message_key'] == 'ACTION NO REFILLS' AND $item['refills_total'] > NO_REFILL) {
        log_error('aborting helper_syncing because NO REFILLS has refills', $item);
        continue;
      }

      if ($item['item_added_by'] == 'MANUAL') {
        log_notice('aborting helper_syncing because item to be REMOVED was added MANUALLY', $item);
        continue;
      }

      $new_count_items--;
      $items_to_sync[]   = ['REMOVE', $item['rx_message_key'], $item];
      $items_to_remove[] = $item['rx_number'];
      //log_notice('sync_to_order removing item', "$item[invoice_number] $item[rx_number] $item[drug], $item[stock_level], $item[rx_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ($item['item_date_added'] AND $item['rx_number'] != $item['best_rx_number']) {

      if ($item['item_added_by'] == 'MANUAL') {
        log_notice('aborting helper_syncing because item to be SWITCHED was added MANUALLY', $item);
        continue;
      }

      $items_to_sync[]   = ['SWITCH', 'RX_NUMBER != BEST_RX_NUMBER', $item];
      $items_to_add[]    = $item['best_rx_number'];
      $items_to_remove[] = $item['rx_number'];
      //log_notice('sync_to_order switching items', "$item[invoice_number] $item[drug] $item[rx_message_key] $item[rx_number] -> $item[best_rx_number]");

      continue;
    }
  }

  if ($items_to_remove)
    export_cp_remove_items($item['invoice_number'], $items_to_remove);

  if ($items_to_add)
    export_cp_add_items($item['invoice_number'], $items_to_add);

  return ['new_count_items' => $new_count_items, 'items_to_sync' => $items_to_sync];
}

//Group all drugs by their next fill date and get the most popular date
function get_sync_to_date($order) {

  $sync_dates = [];
  foreach ($order as $item) {
    if (isset($sync_dates[$item['refill_date_next']]))
      $sync_dates[$item['refill_date_next']][] = $item['best_rx_number']; //rx_number only set if in the order?
    else
      $sync_dates[$item['refill_date_next']] = [];
  }

  $target_date = null;
  $target_rxs  = [];

  foreach($sync_dates as $date => $rx_numbers) {

    $count = count($rx_numbers);
    $target_count = count($target_rxs);

    if ($count > $target_count) {
      $target_date = $date;
      $target_rxs  = $rx_numbers;
    }
    else if ($count == $target_count AND $date > $target_date) { //In case of tie, longest date wins
      $target_date = $date;
      $target_rxs  = $rx_numbers;
    }
  }

  return [$target_date, ','.implode(',', $target_rxs).','];
}

//Sync any drug that has days to the new refill date
function set_sync_to_date($order, $target_date, $target_rxs, $mysql) {

  foreach($order as $i => $item) {

    $old_days_default = $item['days_dispensed_default'];

    //TODO Skip syncing if the drug is OUT OF STOCK (or less than 500 qty?)
    if ( ! $old_days_default OR ! $target_date OR $item['days_dispensed_actual'] OR $item['rx_message_key'] == 'NO ACTION FILL OUT OF STOCK') continue; //Don't add them to order if they are no already in it OR if already dispensed

    $time_refill = $item['refill_date_next'] ? strtotime($item['refill_date_next']) : strtotime($item['item_date_added']); //refill_date_next is sometimes null
    $days_extra  = (strtotime($target_date) - $time_refill)/60/60/24;
    $days_synced = roundDaysUnit($old_days_default + $days_extra);

    $days_left_in_refills    = days_left_in_refills($item);
    $days_left_in_stock      = days_left_in_stock($item);
    $new_days_default        = days_default($days_left_in_refills, $days_left_in_stock, $days_synced, $item);

    if ($days_synced < 0)
      log_error("DAYS SYNCED IS NEGATIVE! days_synced:$days_synced, new_days_default:$new_days_default, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());

    if ($new_days_default != $old_days_default)
      log_notice("set_sync_to_date", ['invoice_number' => $order[0]['invoice_number'], 'drug_generic' => $order[0]['drug_generic'], 'days_extra' => $days_extra, 'days_synced' => $days_synced, 'days_left_in_refills' => $days_left_in_refills, 'days_left_in_stock' => $days_left_in_stock, 'target_date' => "$item[refill_date_next] >>> $target_date", 'days_default' => "$old_days_default >>> $new_days_default"], $order[$i]);

    if ($new_days_default < DAYS_MIN OR $new_days_default > DAYS_MAX OR $new_days_default == $old_days_default) //Limits to the amounts by which we are willing sync
      continue;

    $order[$i]['refill_target_date']      = $target_date;
    $order[$i]['days_dispensed_default']  = $new_days_default;
    $order[$i]['qty_dispensed_default']   = $new_days_default*$item['sig_qty_per_day'];
    $order[$i]['price_dispensed_default'] = ceil($item['price_dispensed'] * $new_days_default / $old_days_default); //Might be null

    //TODO consider making these methods so that they always stay upto date and we don't have to recalcuate them
    $order[$i]['days_dispensed']  = $order[$i]['days_dispensed_actual'] ?: $order[$i]['days_dispensed_default'];
    $order[$i]['qty_dispensed']   = $order[$i]['qty_dispensed_actual'] ?: $order[$i]['qty_dispensed_default'];
    $order[$i]['price_dispensed'] = $order[$i]['price_dispensed_actual'] ?: $order[$i]['price_dispensed_default'];

    $sql = "
      UPDATE
        gp_order_items
      SET
        refill_target_date      = '$target_date',
        refill_target_days      = ".($new_days_default - $old_days_default).",
        refill_target_rxs       = '$target_rxs',
        days_dispensed_default  = $new_days_default,
        qty_dispensed_default   = ".$order[$i]['qty_dispensed_default'].",
        price_dispensed_default = ".$order[$i]['price_dispensed_default']."
      WHERE
        rx_number = $item[rx_number]
    ";

    if ($new_days_default AND ! $order[$i]['qty_dispensed'])
      log_error('helper_syncing: qty_dispensed_default is 0 but days_dispensed_default > 0', [$order[$i], $new_days_default]);

    $mysql->run($sql);

    $order[$i] = export_cp_set_rx_message($order[$i], RX_MESSAGE['NO ACTION SYNC TO DATE'], $mysql);
  }

  return $order;
}
