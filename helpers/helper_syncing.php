<?php

function sync_to_order($order) {

  $add_items = [];

  foreach($order as $item) {

    if ($item['item_date_added']) continue; //Item is already in the order

    if (sync_to_order_past_due($item)) {
      $add_items[] = ['PAST DUE AND SYNC TO ORDER', $item];
      //export_cp_add_item($item, "sync_to_order: PAST DUE AND SYNC TO ORDER");
      continue;
    }

    if (sync_to_order_due_soon($item)) {
      $add_items[] = ['DUE SOON AND SYNC TO ORDER', $item];
      //export_cp_add_item($item, "sync_to_order: DUE SOON AND SYNC TO ORDER");
      continue;
    }
  }

  return $add_items;
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

    $time_refill = $item['refill_date_next'] ? strtotime($item['refill_date_next']) : time(); //refill_date_next is sometimes null
    $days_extra  = (strtotime($target_date) - $time_refill)/60/60/24;
    $days_synced = $old_days_default + round($days_extra/15)*15;

    $new_days_default = days_default($item, $days_synced);

    if ($new_days_default >= 15 AND $new_days_default <= 120 AND $new_days_default != $old_days_default) { //Limits to the amounts by which we are willing sync

      if ($new_days_default <= 30) {
        $new_days_default += 90;
        log_error('debug set_sync_to_date: extra time', get_defined_vars());
      } else {
        log_error('debug set_sync_to_date: std time', get_defined_vars());
      }

      $order[$i]['refill_target_date'] = $target_date;
      $order[$i]['days_dispensed']     = $new_days_default;
      $order[$i]['qty_dispensed']      = $new_days_default*$item['sig_qty_per_day'];
      $order[$i]['price_dispensed']    = ceil($item['price_dispensed'] * $new_days_default / $old_days_default); //Might be null

      $sql = "
        UPDATE
          gp_order_items
        SET
          item_message_key        = 'NO ACTION SYNC TO DATE',
          item_message_text       = '".RX_MESSAGE['NO ACTION SYNC TO DATE'][$item['language']]."',
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

    export_v2_add_pended($order[$i]); //Days should be finalized now
  }

  return $order;
}
