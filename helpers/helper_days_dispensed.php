<?php

//TODO Calculate Qty, Days, & Price

function get_days_default($item) {

  $no_transfer    = is_no_transfer($item);
  $added_manually = is_added_manually($item);
  $not_offered    = is_not_offered($item);
  $refills_only   = is_refill_only($item);

  $days_left_in_expiration = days_left_in_expiration($item);
  $days_left_in_refills    = days_left_in_refills($item);
  $days_left_in_stock      = days_left_in_stock($item);
  $days_default            = days_default($days_left_in_refills, $days_left_in_stock);

  if ( ! $item['sig_qty_per_day']) {
    log_error("helper_days_dispensed: RX WAS NEVER PARSED", $item);
  }

  //#29005 was expired but never dispensed, so check "refill_date_first" so we asking doctors for new rxs that we never dispensed
  if ($item['refill_date_first'] AND ! $item['rx_dispensed_id'] AND $days_left_in_expiration < 0) { // Can't do <= 0 because null <= 0 is true
    log_info("DON'T FILL EXPIRED MEDICATIONS", get_defined_vars());
    return [0, RX_MESSAGE['ACTION EXPIRED']];
  }

  if ( ! $item['drug_gsns']) {
    $item['max_gsn']
      ? log_error("GSN NEEDS TO BE ADDED TO V2", "rx:$item[rx_number] $item[drug_name] $item[drug_generic] rx_gsn:$item[rx_gsn] max_gsn:$item[max_gsn]")
      : log_info("RX IS MISSING GSN", $item);

    return [ $item['refill_date_first'] ? $days_default : 0, RX_MESSAGE['NO ACTION MISSING GSN']];
  }

  if ($item['rx_date_transferred']) {

    if(($item['stock_level_initial'] ?: $item['stock_level']) == STOCK_LEVEL['HIGH SUPPLY'])
      log_error('HIGH STOCK ITEM WAS TRANSFERRED', get_defined_vars());
    else
      log_info("RX WAS ALREADY TRANSFERRED OUT", get_defined_vars());

    return [0, RX_MESSAGE['NO ACTION WAS TRANSFERRED']];
  }

  if ( ! $item['refill_date_first'] AND $not_offered) {
    log_info("TRANSFER OUT NEW RXS THAT WE DONT CARRY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION WILL TRANSFER']];
  }

  if ($no_transfer AND ! $item['refill_date_first'] AND $refills_only) {
    log_info("CHECK BACK IF TRANSFER OUT IS NOT DESIRED", get_defined_vars());
    return [0, RX_MESSAGE['ACTION CHECK BACK']];
  }

  if ( ! $no_transfer AND ! $item['refill_date_first'] AND $refills_only) {
    log_info("TRANSFER OUT NEW RXS THAT WE CANT FILL", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION WILL TRANSFER CHECK BACK']];
  }

  if ( ! $item['rx_dispensed_id'] AND $item['refills_total'] < 0.1) { //Unlike refills_dispensed_default/actual might not be set yet
    log_info("DON'T FILL MEDICATIONS WITHOUT REFILLS", $item);
    return [0, RX_MESSAGE['ACTION NO REFILLS']];
  }

  if ( ! $item['pharmacy_name']) {
    log_info("PATIENT NEEDS TO REGISTER", get_defined_vars());
    return [$item['item_date_added'] ? $days_default : 0, RX_MESSAGE['ACTION NEEDS FORM']];
  }

  if ( ! $item['patient_autofill'] AND ! $added_manually) {
    log_info("DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED", get_defined_vars());
    return [0, RX_MESSAGE['ACTION PATIENT OFF AUTOFILL']];
  }

  if ( ! $item['patient_autofill'] AND $added_manually) {
    log_info("OVERRIDE PATIENT AUTOFILL OFF SINCE MANUALLY ADDED", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION RX OFF AUTOFILL']];
  }

  if ((strtotime($item['refill_date_next']) - strtotime($item['order_date_added'])) > 15*24*60*60 AND ! $added_manually) {

    //DON'T STRICTLY NEED THIS TEST BUT IT GIVES A MORE SPECIFIC ERROR SO IT MIGHT BE HELPFUL
    if ((strtotime($item['order_date_added']) - strtotime($item['refill_date_last'])) < 15*24*60*60 AND ! $added_manually) {
      log_info("DON'T REFILL IF FILLED WITHIN LAST 15 DAYS UNLESS ADDED MANUALLY", get_defined_vars());
      return [0, RX_MESSAGE['NO ACTION RECENT FILL']];
    }

    log_info("DON'T REFILL IF NOT DUE IN OVER 15 DAYS UNLESS ADDED MANUALLY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION NOT DUE']];
  }

  if ( ! $item['refill_date_first'] AND $item['qty_inventory'] < 2000 AND ($item['sig_qty_per_day'] > 2.5*($item['qty_repack'] ?: 135)) AND ! $added_manually) {
    log_info("SIG SEEMS TO HAVE EXCESSIVE QTY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION CHECK SIG']];
  }

  //If SureScript comes in AND only *rx* is off autofill, we assume patients wants it.
  //This is different when *patient* is off autofill, then we assume they don't want it unless added_manually
  if ( ! $item['rx_autofill'] AND ! $item['item_date_added']) {
    log_info("DON'T FILL IF RX_AUTOFILL IS OFF AND NOT IN ORDER", get_defined_vars());
    return [0, RX_MESSAGE['ACTION RX OFF AUTOFILL']];
  }

  if ( ! $item['rx_autofill'] AND $item['item_date_added']) {
    log_info("OVERRIDE RX AUTOFILL OFF", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION RX OFF AUTOFILL']];
  }

  if ($item['refill_date_first'] AND $not_offered) {
    log_info("REFILLS SHOULD NOT HAVE A NOT OFFERED STATUS", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION NEW GSN']];
  }

  if (sync_to_order_new_rx($item, $order)) {
    log_info('NO ACTION NEW RX SYNCED TO ORDER', get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION NEW RX SYNCED TO ORDER']];
  }

  //TODO and check if added by this program otherwise false positives
  if (sync_to_order_past_due($item, $order)) {
    log_info("WAS PAST DUE SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION PAST DUE AND SYNC TO ORDER']];
  }

  //TODO CHECK IF THIS IS A GUARDIAN ERROR OR WHETHER WE ARE IMPORTING WRONG.  SEEMS THAT IF REFILL_DATE_FIRST IS SET, THEN REFILL_DATE_DEFAULT should be set
  if (sync_to_order_missing_next($item, $order)) {
    log_info("WAS MISSING REFILL_DATE_NEXT SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION MISSING NEXT AND SYNC TO ORDER']];
  }

  //TODO and check if added by this program otherwise false positives
  if (sync_to_order_due_soon($item, $order)) {
    log_info("WAS DUE SOON SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION DUE SOON AND SYNC TO ORDER']];
  }

  if ($days_left_in_expiration) {
    log_info("WARN USERS IF RX IS ABOUT TO EXPIRE", get_defined_vars());
    return [$days_left_in_expiration, RX_MESSAGE['ACTION EXPIRING']];
  }

  if ($days_left_in_refills == $days_default) {
    log_info("WARN USERS IF DRUG IS ON LAST REFILL", get_defined_vars());
    return [$days_default, RX_MESSAGE['ACTION LAST REFILL']];
  }

  if ($days_left_in_stock == $days_default) {

    if ($item['refill_date_first'])
      log_error("YIKES! IS REFILL RX IS OUT OF STOCK?", get_defined_vars());
    else
      log_notice("WARN USERS IF DRUG IS LOW QTY", get_defined_vars());

    return [$days_default, RX_MESSAGE['NO ACTION LOW STOCK']];
  }

  log_info("NO SPECIAL RX_MESSAGE USING DEFAULTS", get_defined_vars());
  return [$days_default, RX_MESSAGE['NO ACTION STANDARD FILL']];
  //TODO DON'T NO ACTION_PAST_DUE if ( ! drug.$InOrder AND drug.$DaysToRefill < 0)
  //TODO NO ACTION_LIVE_INVENTORY_ERROR if ( ! drug.$v2)
  //TODO ACTION_CHECK_BACK/NO ACTION_WILL_TRANSFER_CHECK_BACK
  //if ( ! drug.$IsRefill AND ! drug.$IsPended AND ~ ['Out of Stock', 'Refills Only'].indexOf(drug.$Stock))
  //if (drug.$NoTransfer)
}

function set_price_refills_actual($item, $mysql) {

  if ( ! $item['days_dispensed_actual'])
    return log_error("set_price_refills_actual has no actual days", get_defined_vars());

  $price_per_month = $item['price_per_month'] ?: 0; //Might be null
  $price_actual    = ceil($item['days_dispensed_actual']*$price_per_month/30);

  if ($price_actual > 80)
    return log_error("set_price_refills_actual: too high", get_defined_vars());

  $sql = "
    UPDATE
      gp_order_items
    SET
      -- Other Fields Should Already Be Set Above (And May have Been Sent to Patient) so don't change
      price_dispensed_actual   = $price_actual,
      refills_dispensed_actual = $item[refills_total]
    WHERE
      invoice_number = $item[invoice_number] AND
      rx_number = $item[rx_number]
  ";

  $mysql->run($sql);
}

function set_days_default($item, $days, $message, $mysql) {

  $old_item_message_key  = $item['item_message_key'];
  $old_item_message_text = $item['item_message_text'];

  $item['item_message_key']  = array_search($message, RX_MESSAGE);
  $item['item_message_text'] = message_text($message, $item);

  if ( ! $item['item_message_key']) {
    log_error("set_days_default could not get item_message_key ", get_defined_vars());
    return $item;
  }

  if ( ! $days)
    $item['item_message_text'] .= ' **'; //If not filling reference to backup pharmacy footnote on Invoices

  if (is_null($days)) {
    log_error("set_days_default days should not be NULL", get_defined_vars());
  }

  //We can only save it if its an order_item that's not yet dispensed
  if ( ! $days AND ! $item['item_date_added'])
    return $item; //We can only save for items in order (order_items)

  if ($days AND ! $item['item_date_added']) {

    //TODO Still investigating Most likely we already sent out a patient communiction saying what was in the order and we didn't want to add this one in and retell the patient
    if ( ! in_array($item['item_message_key'], ['NO ACTION PAST DUE AND SYNC TO ORDER', 'NO ACTION DUE SOON AND SYNC TO ORDER', 'NO ACTION NEW RX SYNCED TO ORDER'])) {
      log_error("helper_days_dispensed set_days_default: $item[item_message_key]. days is being set to item that is not in order. Hopefully this synced to order later?", get_defined_vars());
    }
    return $item; //We can only save for items in order (order_items)
  }

  if ($item['days_dispensed_actual']) {
    log_notice("set_days_default but it has actual days", get_defined_vars());
    return $item;
  }

  if ( ! $item['rx_number'] OR ! $item['invoice_number']) {
    log_error("set_days_default without a rx_number AND invoice_number ", get_defined_vars());
    return $item;
  }

  if ($days AND $item['days_dispensed_default']) {
    log_error('ERROR set_days_default. days_dispensed_default is already do not overwrite (unless with a 0)', get_defined_vars());
    return $item;
  }

  $exists = $mysql->run("
    SELECT *
    FROM
      gp_order_items
    WHERE
      invoice_number = $item[invoice_number] AND
      rx_number = $item[rx_number]
  ");

  if ( ! count($exists[0])) {
    log_error("set_days_default cannot set days for invoice_number = $item[invoice_number] AND rx_number = $item[rx_number]", get_defined_vars());
    return $item;
  }

  $price = $item['price_per_month'] ?: 0; //Might be null

  $item['days_dispensed_default']    = $days;
  $item['qty_dispensed_default']     = $days*$item['sig_qty_per_day'];
  $item['price_dispensed_default']   = ceil($days*$price/30);
  $item['refills_dispensed_default'] = max(0, $item['refills_total'] - ($days ? 1 : 0));  //We want invoice to show refills after they are dispensed assuming we dispense items currently in order
  $item['stock_level_initial']       = $item['stock_level'];


  $sql = "
    UPDATE
      gp_order_items
    SET
      days_dispensed_default    = $days,
      qty_dispensed_default     = $item[qty_dispensed_default],
      item_message_key          = '$item[item_message_key]',
      item_message_text         = '".@mysql_escape_string($item['item_message_text'])."',
      price_dispensed_default   = $item[price_dispensed_default],
      refills_dispensed_default = $item[refills_dispensed_default],
      stock_level_initial       = '$item[stock_level_initial]',
      refill_date_manual        = ".($item['refill_date_manual'] ? "'$item[refill_date_manual]'" : 'NULL').",
      refill_date_default       = ".($item['refill_date_default'] ? "'$item[refill_date_default]'" : 'NULL').",
      refill_date_last          = ".($item['refill_date_last'] ? "'$item[refill_date_last]'" : 'NULL')."
    WHERE
      invoice_number = $item[invoice_number] AND
      rx_number = $item[rx_number]
  ";

  $mysql->run($sql);

  return $item;
}

//TODO OR IT'S AN OTC
function is_no_transfer($item) {
  return $item['price_per_month'] >= 20 OR $item['pharmacy_phone'] == "8889875187";
}

function is_added_manually($item) {
  return in_array($item['item_added_by'], ADDED_MANUALLY);
}

function is_not_offered($item) {
  $stock_level = $item['stock_level_initial'] ?: $item['stock_level'];

  $not_offered = is_null($stock_level) OR ($stock_level == STOCK_LEVEL['NOT OFFERED']);

  if ($not_offered) //TODO Alert here is drug is not offered but has a qty_inventory > 500
    log_notice('is_not_offered: true', [get_defined_vars(), "$stock_level == ".STOCK_LEVEL['NOT OFFERED']]);
  else
    log_notice('is_not_offered: false', [get_defined_vars(), "$stock_level == ".STOCK_LEVEL['NOT OFFERED']]);

  return $not_offered;
}

function is_refill_only($item) {
  return in_array($item['stock_level_initial'] ?: $item['stock_level'], [
    STOCK_LEVEL['OUT OF STOCK'],
    STOCK_LEVEL['REFILL ONLY']
  ]);
}

function message_text($message, $item) {
  return str_replace(array_keys($item), array_values($item), $message[$item['language']]);
}

function sync_to_order_new_rx($item, $order) {
  $not_offered  = is_not_offered($item);
  $refills_only = is_refill_only($item);
  $eligible     = ! $item['item_date_added'] AND $item['refills_total'] >= 0.1 AND ! $item['refill_date_first'] AND $item['rx_autofill'] AND ! $not_offered AND ! $refills_only;
  return $eligible AND ! is_duplicate_gsn($item, $order);
}

function sync_to_order_past_due($item, $order) {
  $eligible = ! $item['item_date_added'] AND $item['refills_total'] >= 0.1 AND $item['refill_date_next'] AND (strtotime($item['refill_date_next']) - strtotime($item['order_date_added'])) < 0;
  return $eligible AND ! is_duplicate_gsn($item, $order);
}

//Order 29017 had a refill_date_first and rx/pat_autofill ON but was missing a refill_date_default/refill_date_manual/refill_date_next
function sync_to_order_missing_next($item, $order) {
  $eligible = ! $item['item_date_added'] AND $item['refills_total'] >= 0.1 AND $item['refill_date_first'] AND ! $item['refill_date_default'];
  return $eligible AND ! is_duplicate_gsn($item, $order);
}

function sync_to_order_due_soon($item, $order) {
  $eligible = ! $item['item_date_added'] AND $item['refills_total'] >= 0.1 AND $item['refill_date_next'] AND (strtotime($item['refill_date_next'])  - strtotime($item['order_date_added'])) <= 15*24*60*60;
  return $eligible AND ! is_duplicate_gsn($item, $order);
}

//Although you can dispense up until an Rx expires (so refill_date_next is well past rx_date_expired) we want to use
//as much of a Rx as possible, so if it will expire before  the standard dispense date than dispense everything left
function days_left_in_expiration($item) {

  $days_left_in_expiration = (strtotime($item['rx_date_expired']) - strtotime($item['refill_date_next']))/60/60/24;

  if ($days_left_in_expiration <= DAYS_STD) return round15($item['qty_left']/$item['sig_qty_per_day']);
}

function days_left_in_refills($item) {

  $days_left_in_refills = $item['qty_left']/$item['sig_qty_per_day'];

  //Fill up to 30 days more to finish up an Rx if almost finished.
  //E.g., If 30 day script with 3 refills (4 fills total, 120 days total) then we want to 1x 120 and not 1x 90 + 1x30
  if ($days_left_in_refills <= DAYS_STD+30) return round15($days_left_in_refills);
}

function days_left_in_stock($item) {

  $days_left_in_stock = round($item['qty_inventory']/$item['sig_qty_per_day']);

  if (($item['sig_qty_per_day'] <= 10 AND $days_left_in_stock < DAYS_STD) OR $item['qty_inventory'] < 500) {

    if(($item['stock_level_initial'] ?: $item['stock_level']) == STOCK_LEVEL['HIGH SUPPLY'] AND $item['sig_qty_per_day'] != round(1/30, 3))
      log_error("LOW STOCK ITEM IS MARKED HIGH SUPPLY $item[drug_generic] days_left_in_stock:$days_left_in_stock qty_inventory:$item[qty_inventory]", get_defined_vars());

    return $item['sig_qty_per_day'] == round(1/30, 3) ? 60.6 : 45; //Dispensed 2 inhalers per time, since 1/30 is rounded to 3 decimals (.033), 2 month/.033 = 60.6 qty
  }
}

function round15($days) {
  return floor($days/15)*15;
}

//Days is basically the MIN(target_date ?: std_day, qty_left as days, inventory_left as days).
//NOTE: We adjust bump up the days by upto 30 in order to finish up an Rx (we don't want partial fills left)
//NOTE: We base this on the best_rx_number and NOT on the rx currently in the order
function days_default($days_left_in_refills, $days_left_in_stock, $days_default = DAYS_STD) {

  //Cannot have NULL inside of MIN()
  $days_default = min(
    $days_left_in_refills ?: $days_default,
    $days_left_in_stock ?: $days_default
  );

  if ($days_default % 15)
    log_error("DEFAULT DAYS IS NOT A MULTIPLE OF 15! days_default:$days_default, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());
  else
    log_info("days_default:$days_default, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());

  return $days_default;
}

//Don't sync if an order with these instructions already exists in order
function is_duplicate_gsn($item1, $order) {
  //Don't sync if an order with these instructions already exists in order
  foreach($order as $item2) {
    if ($item1 !== $item2 AND $item2['item_date_added'] AND $item1['drug_gsns'] == $item2['drug_gsns']) {
      log_notice("helper_days_dispensed syncing item: matching drug_gsns so did not SYNC TO ORDER' $item1[invoice_number] $item1[drug] $item1[item_message_key] refills last:$item1[refill_date_last] next:$item1[refill_date_next] total:$item1[refills_total] left:$item1[refills_left]", ['item1' => $item1, 'item2' => $item2]);
      return true;
    }
  }
}
