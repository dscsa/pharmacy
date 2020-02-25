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
  $days_default            = days_default($days_left_in_expiration, $days_left_in_refills, $days_left_in_stock);

  if ( ! $item['rx_dispensed_id'] AND $days_left_in_expiration < 0) { // Can't do <= 0 because null <= 0 is true
    log_info("DON'T FILL EXPIRED MEDICATIONS", get_defined_vars());
    return [0, RX_MESSAGE['ACTION EXPIRED']];
  }

  if ( ! $item['rx_dispensed_id'] AND $item['refills_total'] < 0.1) { //Unlike refills_dispensed_default/actual might not be set yet
    log_info("DON'T FILL MEDICATIONS WITHOUT REFILLS", $item);
    return [0, RX_MESSAGE['ACTION NO REFILLS']];
  }

  if ( ! $item['drug_gsns']) {
    $item['max_gsn']
      ? log_error("GSN NEEDS TO BE ADDED TO V2", "rx:$item[rx_number] $item[drug_name] $item[drug_generic] rx_gsn:$item[rx_gsn] max_gsn:$item[max_gsn]")
      : log_info("RX IS MISSING GSN", $item);

    return [ $item['refill_date_first'] ? days_default($item) : 0, RX_MESSAGE['NO ACTION MISSING GSN']];
  }

  if ($item['rx_date_transferred']) {
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

  //TODO MAYBE WE SHOULD JUST MOVE THE REFILL_DATE_NEXT BACK BY A WEEK OR TWO
  if ($item['refill_date_first'] AND ($item['qty_inventory']/$item['sig_qty_per_day'] < 30) AND ! $manual) {
    log_info("CHECK BACK NOT ENOUGH QTY UNLESS ADDED MANUALLY", get_defined_vars());
    return [0, RX_MESSAGE['ACTION CHECK BACK']];
  }

  if ( ! $item['pharmacy_name']) {
    log_info("PATIENT NEEDS TO REGISTER", get_defined_vars());
    return [$days_default, RX_MESSAGE['ACTION NEEDS FORM']];
  }

  if ( ! $item['patient_autofill'] AND ! $manual) {
    log_info("DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED", get_defined_vars());
    return [0, RX_MESSAGE['ACTION PATIENT OFF AUTOFILL']];
  }

  if ( ! $item['patient_autofill'] AND $manual) {
    log_info("OVERRIDE PATIENT AUTOFILL OFF SINCE MANUALLY ADDED", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION RX OFF AUTOFILL']];
  }

  if ((strtotime($item['refill_date_next']) - strtotime($item['order_date_added'])) > 15*24*60*60 AND ! $manual) {

    //DON'T STRICTLY NEED THIS TEST BUT IT GIVES A MORE SPECIFIC ERROR SO IT MIGHT BE HELPFUL
    if ((strtotime($item['order_date_added']) - strtotime($item['refill_date_last'])) < 15*24*60*60 AND ! $manual) {
      log_info("DON'T REFILL IF FILLED WITHIN LAST 15 DAYS UNLESS ADDED MANUALLY", get_defined_vars());
      return [0, RX_MESSAGE['NO ACTION RECENT FILL']];
    }

    log_info("DON'T REFILL IF NOT DUE IN OVER 15 DAYS UNLESS ADDED MANUALLY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION NOT DUE']];
  }

  if ( ! $item['refill_date_first'] AND $item['qty_inventory'] < 2000 AND ($item['sig_qty_per_day'] > 2.5*($item['qty_repack'] ?: 135)) AND ! $manual) {
    log_info("SIG SEEMS TO HAVE EXCESSIVE QTY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION CHECK SIG']];
  }

  if ( ! $item['refill_date_first'] AND $not_offered) {
    log_info("REFILLS SHOULD NOT HAVE A NOT OFFERED STATUS", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION NEW GSN']];
  }

  if ( ! $item['rx_autofill']) { //InOrder is implied here
    log_info("IF RX IS IN ORDER FILL IT EVEN IF RX_AUTOFILL IS OFF", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION RX OFF AUTOFILL']];
  }

  if (sync_to_order_new_rx($item)) {
    log_info('NO ACTION NEW RX SYNCED TO ORDER', get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION NEW RX SYNCED TO ORDER']];
  }

  //TODO and check if added by this program otherwise false positives
  if (sync_to_order_past_due($item)) {
    log_info("WAS PAST DUE SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION PAST DUE AND SYNC TO ORDER']];
  }

  //TODO and check if added by this program otherwise false positives
  if (sync_to_order_due_soon($item)) {
    log_info("WAS DUE SOON SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION DUE SOON AND SYNC TO ORDER']];
  }

  if ($days_left_in_expiration == $days_default) {
    log_info("WARN USERS IF RX IS ABOUT TO EXPIRE", get_defined_vars());
    return [$days_default, RX_MESSAGE['ACTION EXPIRING']];
  }

  if ($days_left_in_refills == $days_default) {
    log_info("WARN USERS IF DRUG IS LOW QTY", get_defined_vars());
    return [$days_default, RX_MESSAGE['ACTION LAST REFILL']];
  }

  if ($days_left_in_stock == $days_default) {
    log_info("WARN USERS IF DRUG IS LOW QTY", get_defined_vars());
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
  return $item['stock_level'] == STOCK_LEVEL['NOT OFFERED'];
}

function is_refill_only($item) {
  return in_array($item['stock_level'], [
    STOCK_LEVEL['OUT OF STOCK'],
    STOCK_LEVEL['REFILL ONLY']
  ]);
}

function message_text($message, $item) {
  return str_replace(array_keys($item), array_values($item), $message[$item['language']]);
}

function sync_to_order_new_rx($item) {
  $not_offered  = is_not_offered($item);
  $refills_only = is_refill_only($item);

  return  ! $item['item_date_added'] AND $item['refills_total'] >= 0.1 AND ! $item['refill_date_first'] AND $item['rx_autofill'] AND ! $not_offered AND ! $refills_only;
}

function sync_to_order_past_due($item) {
  return  ! $item['item_date_added'] AND $item['refills_total'] >= 0.1 AND $item['refill_date_next'] AND (strtotime($item['refill_date_next']) - strtotime($item['order_date_added'])) < 0;
}

function sync_to_order_due_soon($item) {
  return ! $item['item_date_added'] AND $item['refills_total'] >= 0.1 AND $item['refill_date_next'] AND (strtotime($item['refill_date_next'])  - strtotime($item['order_date_added'])) <= 15*24*60*60;
}

function days_left_in_expiration($item) {

  $days_left_in_expiration = strtotime($item['rx_date_expired']) - strtotime($item['refill_date_next']);

  if ($days_left_in_expiration <= DAYS_STD+30) return $days_left_in_expiration;
}

function days_left_in_refills($item) {

  $days_left_in_refills = round($item['qty_left']/$item['sig_qty_per_day']);

  //Fill up to 30 days more to finish up an Rx if almost finished.
  //E.g., If 30 day script with 3 refills (4 fills total, 120 days total) then we want to 1x 120 and not 1x 90 + 1x30
  if ($days_left_in_refills <= DAYS_STD+30) return $days_left_in_refills;
}

function days_left_in_stock($item) {

  $days_left_in_stock = round($item['qty_inventory']/$item['sig_qty_per_day']);

  if ($days_left_in_stock < DAYS_STD OR $item['qty_inventory'] < 500) {

    if($item['stock_level'] == STOCK_LEVEL['HIGH SUPPLY'] AND $item['sig_qty_per_day'] != 1/30)
      log_error('LOW STOCK ITEM IS MARKED HIGH SUPPLY', get_defined_vars());

    return $item['sig_qty_per_day'] == 1/30 ? 30 : 45; //Assume an Inhaler lasts one month
  }
}

//Days is basically the MIN(target_date ?: std_day, qty_left as days, inventory_left as days).
//NOTE: We adjust bump up the days by upto 30 in order to finish up an Rx (we don't want partial fills left)
//NOTE: We base this on the best_rx_number and NOT on the rx currently in the order
function days_default($days_left_in_expiration, $days_left_in_refills, $days_left_in_stock) {

  //Cannot have NULL inside of MIN()
  $days_default = min(
    $days_left_in_expiration ?: $days_default,
    $days_left_in_refills ?: $days_default,
    $days_left_in_stock ?: $days_default,
    DAYS_STD
  );

  if ($days_default % 15)
    log_error("DEFAULT DAYS IS NOT A MULTIPLE OF 15! days_default:$days_default, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());
  else
    log_info("days_default:$days_default, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());

  return $days_default;
}
