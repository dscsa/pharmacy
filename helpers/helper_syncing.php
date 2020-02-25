<?php

//Remove Only flag so that once we communicate what's in an order to a patient we "lock" it, so that if new SureScript come in we remove them so we don't surprise a patient with new drugs
function sync_to_order($order, $updated = null) {

  $items_to_sync   = [];
  $items_to_add    = [];
  $items_to_remove = [];

  foreach($order as $item) {

    if ($item['rx_dispensed_id']) {
      log_info('syncing item canceled because already dispensed', $item);
      continue;
    }

    if ($item['item_message_key'] == 'NO ACTION PAST DUE AND SYNC TO ORDER' AND ! is_duplicate_gsn($order, $item)) {

      if ($updated) {
        log_error("sync_to_order adding item: updated so did not add 'NO ACTION PAST DUE AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[item_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);
        continue;
      }

      $items_to_sync[] = ['ADD', 'NO ACTION PAST DUE AND SYNC TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];
      //log_notice('sync_to_order adding item: PAST DUE AND SYNC TO ORDER', "$item[invoice_number] $item[drug] $item[item_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ($item['item_message_key'] == 'NO ACTION DUE SOON AND SYNC TO ORDER' AND ! is_duplicate_gsn($order, $item)) {

      if ($updated) {
        log_error("sync_to_order adding item: updated so did not add 'NO ACTION DUE SOON AND SYNC TO ORDER' $item[invoice_number] $item[drug] $item[item_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);
        continue;
      }

      $items_to_sync[] = ['ADD', 'NO ACTION DUE SOON AND SYNC TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];
      //log_notice('sync_to_order adding item: DUE SOON AND SYNC TO ORDER', "$item[invoice_number] $item[drug] $item[item_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ($item['item_message_key'] == 'NO ACTION NEW RX SYNCED TO ORDER' AND ! is_duplicate_gsn($order, $item)) {

      if ($updated) {
        if ($item['drug_gsns'])
          log_error("sync_to_order adding item: updated so did not add 'NO ACTION NEW RX SYNCED TO ORDER' $item[invoice_number] $item[drug] $item[item_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);

        continue;
      }

      $items_to_sync[] = ['ADD', 'NO ACTION NEW RX SYNCED TO ORDER', $item];
      $items_to_add [] = $item['best_rx_number'];

      continue;
    }

    //Don't remove items with a missing GSN as this is something we need to do
    if ($item['item_date_added'] AND $item['item_added_by'] != 'MANUAL' AND ! $item['days_dispensed'] AND $item['drug_gsns']) {

      //DEBUG CODE SHOULD NOT BE NEEDED
      if ($item['item_message_key'] == 'ACTION NO REFILLS' AND ! $item['rx_dispensed_id'] AND $item['refills_total'] >= 0.1) {
        log_error('aborting helper_syncing because NO REFILLS has refills', $item);
        continue;
      }

      $items_to_sync[]   = ['REMOVE', $item['item_message_key'], $item];
      $items_to_remove[] = $item['rx_number'];
      //log_notice('sync_to_order removing item', "$item[invoice_number] $item[rx_number] $item[drug], $item[stock_level], $item[item_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]");

      continue;
    }

    if ($item['item_date_added'] AND $item['item_added_by'] != 'MANUAL' AND $item['rx_number'] != $item['best_rx_number']) {
      $items_to_sync[]   = ['SWITCH', 'RX_NUMBER != BEST_RX_NUMBER', $item];
      $items_to_add[]    = $item['best_rx_number'];
      $items_to_remove[] = $item['rx_number'];
      //log_notice('sync_to_order switching items', "$item[invoice_number] $item[drug] $item[item_message_key] $item[rx_number] -> $item[best_rx_number]");

      continue;
    }
  }

  if ($items_to_remove)
    export_cp_remove_items($item['invoice_number'], $items_to_remove);

  if ($items_to_add)
    export_cp_add_items($item['invoice_number'], $items_to_add);

  return $items_to_sync;
}

//Don't sync if an order with these instructions already exists in order
function is_duplicate_gsn($order, $item1) {
  //Don't sync if an order with these instructions already exists in order
  foreach($order as $item2) {
    if ($item1 !== $item2 AND $item['drug_gsns'] == $item2['drug_gsns']) {
      log_error("sync_to_order adding item: matching drug_gsns so did not add 'NO ACTION NEW RX SYNCED TO ORDER' $item[invoice_number] $item[drug] $item[item_message_key] refills last:$item[refill_date_last] next:$item[refill_date_next] total:$item[refills_total] left:$item[refills_left]", [$item, $updated]);
      return true;
    }
  }

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
    if ( ! $old_days_default OR $item['days_dispensed_actual'] OR $item['item_message_key'] == 'NO ACTION LOW STOCK') continue; //Don't add them to order if they are no already in it OR if already dispensed

    $time_refill = $item['refill_date_next'] ? strtotime($item['refill_date_next']) : strtotime($item['item_date_added']); //refill_date_next is sometimes null
    $days_extra  = (strtotime($target_date) - $time_refill)/60/60/24;
    $days_synced = $old_days_default + round($days_extra/15)*15;

    $days_left_in_expiration = days_left_in_expiration($item);
    $days_left_in_refills    = days_left_in_refills($item);
    $days_left_in_stock      = days_left_in_stock($item);
    $new_days_default        = days_default($days_left_in_expiration, $days_left_in_refills, $days_left_in_stock);

    if ($new_days_default >= 15 AND $new_days_default <= 120 AND $new_days_default != $old_days_default) { //Limits to the amounts by which we are willing sync

      if ($new_days_default <= 30) {
        $new_days_default += DAYS_STD;
        log_info('debug set_sync_to_date: extra time', get_defined_vars());
      } else {
        log_info('debug set_sync_to_date: std time', get_defined_vars());
      }

      $order[$i]['refill_target_date'] = $target_date;
      $order[$i]['days_dispensed']     = $new_days_default;
      $order[$i]['qty_dispensed']      = $new_days_default*$item['sig_qty_per_day'];
      $order[$i]['price_dispensed']    = ceil($item['price_dispensed'] * $new_days_default / $old_days_default); //Might be null
      $order[$i]['item_message_key']   = 'NO ACTION SYNC TO DATE';
      $order[$i]['item_message_text']  = message_text(RX_MESSAGE['NO ACTION SYNC TO DATE'], $order[$i]);

      $sql = "
        UPDATE
          gp_order_items
        SET
          item_message_key        = '".$order[$i]['item_message_key']."',
          item_message_text       = '".$order[$i]['item_message_text']."',
          refill_target_date      = '$target_date',
          refill_target_days      = ".($new_days_default - $old_days_default).",
          refill_target_rxs       = '$target_rxs',
          days_dispensed_default  = $new_days_default,
          qty_dispensed_default   = ".$order[$i]['qty_dispensed'].",
          price_dispensed_default = ".$order[$i]['price_dispensed']."
        WHERE
          rx_number = $item[rx_number]
      ";

      $mysql->run($sql);
    }

    export_v2_add_pended($order[$i], $mysql); //Days should be finalized now
  }

  return $order;
}
