<?php

//TODO Calculate Qty, Days, & Price

function get_days_default($item) {

  log_info("get_days_default", get_defined_vars());//.print_r($item, true);

  //TODO OR IT'S AN OTC
  $no_transfer = $item['price_per_month'] >= 20 OR $item['pharmacy_phone'] == "8889875187";

  $manual = in_array($item['item_added_by'], ADDED_MANUALLY);

  $not_offered = $item['stock_level'] == STOCK_LEVEL['NOT OFFERED'];

  $refills_only = is_refill_only($item);

  //ALTERNATIVE: $item['days_left'] <= 0; but doesn't seem to always exist
  if ($item['rx_date_expired'] < $item['refill_date_next']) {
    log_info("DON'T FILL EXPIRED MEDICATIONS", get_defined_vars());
    return [0, RX_MESSAGE['ACTION EXPIRED']];
  }

  if ($item['refills_total'] < 0.1) {
    log_info("DON'T FILL MEDICATIONS WITHOUT REFILLS", get_defined_vars());
    return [0, RX_MESSAGE['ACTION NO REFILLS']];
  }

  if ( ! $item['drug_gsns']) {
    log_error("CAN'T FILL MEDICATIONS WITHOUT A GSN MATCH", get_defined_vars());
    return [ $item['refill_date_first'] ? days_default($item) : 0, RX_MESSAGE['NO ACTION MISSING GSN']];
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

  if (is_null($item['patient_autofill'])) {
    log_info("PATIENT NEEDS TO REGISTER", get_defined_vars());
    return [0, RX_MESSAGE['ACTION NEEDS FORM']];
  }

  if ( ! $item['patient_autofill'] AND ! $manual) {
    log_info("DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED", get_defined_vars());
    return [0, RX_MESSAGE['ACTION PATIENT OFF AUTOFILL']];
  }

  if ( ! $item['patient_autofill'] AND $manual) {
    log_info("OVERRIDE PATIENT AUTOFILL OFF SINCE MANUALLY ADDED", get_defined_vars());
    return [days_default($item), RX_MESSAGE['NO ACTION RX OFF AUTOFILL']];
  }

  if ((strtotime($item['refill_date_next']) - strtotime($item['item_date_added'])) > 15*24*60*60 AND ! $manual) {

    //DON'T STRICTLY NEED THIS TEST BUT IT GIVES A MORE SPECIFIC ERROR SO IT MIGHT BE HELPFUL
    if ((strtotime($item['item_date_added']) - strtotime($item['refill_date_last'])) < 15*24*60*60 AND ! $manual) {
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
    return [days_default($item), RX_MESSAGE['NO ACTION LIVE INVENTORY ERROR']];
  }

  if ( ! $item['rx_autofill']) { //InOrder is implied here
    log_info("IF RX IS IN ORDER FILL IT EVEN IF RX_AUTOFILL IS OFF", get_defined_vars());
    return [days_default($item), RX_MESSAGE['NO ACTION RX OFF AUTOFILL']];
  }

  if ((strtotime($item['rx_date_expired']) - strtotime($item['refill_date_next'])) < 45*24*60*60) {
    log_info("WARN USERS IF RX IS ABOUT TO EXPIRE", get_defined_vars());
    return [days_default($item), RX_MESSAGE['ACTION EXPIRING']];
  }

  $days_left_in_rx = days_left_in_rx($item);
  if ($days_left_in_rx) {
    log_info("WARN USERS IF DRUG IS LOW QTY", get_defined_vars());
    return [$days_left_in_rx, RX_MESSAGE['ACTION LAST REFILL']];
  }

  $days_left_in_stock = days_left_in_stock($item);
  if ($days_left_in_stock) {
    log_info("WARN USERS IF DRUG IS LOW QTY", get_defined_vars());
    return [$days_left_in_stock, RX_MESSAGE['NO ACTION LOW STOCK']];
  }

  //TODO and check if added by this program otherwise false positives
  if (sync_to_order_past_due($item)) {
    log_error("PAST DUE AND SYNC TO ORDER", get_defined_vars());
    return [days_default($item), RX_MESSAGE['PAST DUE AND SYNC TO ORDER']];
  }

  //TODO and check if added by this program otherwise false positives
  if (sync_to_order_due_soon($item)) {
    log_error("DUE SOON AND SYNC TO ORDER", get_defined_vars());
    return [days_default($item), RX_MESSAGE['DUE SOON AND SYNC TO ORDER']];
  }

  log_info("NO SPECIAL RX_MESSAGE USING DEFAULTS", get_defined_vars());
  return [days_default($item), RX_MESSAGE['NO ACTION STANDARD FILL']];
  //TODO DON'T NO ACTION_PAST_DUE if ( ! drug.$InOrder AND drug.$DaysToRefill < 0)
  //TODO NO ACTION_LIVE_INVENTORY_ERROR if ( ! drug.$v2)
  //TODO ACTION_CHECK_BACK/NO ACTION_WILL_TRANSFER_CHECK_BACK
  //if ( ! drug.$IsRefill AND ! drug.$IsPended AND ~ ['Out of Stock', 'Refills Only'].indexOf(drug.$Stock))
  //if (drug.$NoTransfer)
}

function set_days_actual($item, $mysql) {

  if ( ! $item['days_dispensed_actual'])
    return log_error("set_days_actual has no actual days", get_defined_vars());

  $price = $item['price_per_month'] ?: 0; //Might be null

  $sql = "
    UPDATE
      gp_order_items
    SET
      -- Other Fields Should Already Be Set Above (And May have Been Sent to Patient) so don't change
      price_dispensed_actual   = ".ceil($item['days_dispensed_actual']*$price/30).",
      refills_total_actual     = $item[refills_total]
    WHERE
      invoice_number = $item[invoice_number] AND
      rx_number = $item[rx_number]
  ";

  $mysql->run($sql);
}

function set_days_default($item, $days, $message, $mysql) {

  $price = $item['price_per_month'] ?: 0; //Might be null

  if ( ! $item['rx_number'] OR ! $item['invoice_number'] ) {
    log_error("set_days_default without a rx_number AND invoice_number ", get_defined_vars());
  }
  else if ($item['days_dispensed_actual']) {
    log_error("set_days_default but it has actual days", get_defined_vars());
  }
  else if ( ! $item['days_dispensed_default']) {

    $message_key  = array_search($message, RX_MESSAGE);
    $message_text = message_text($message, $item);

    $sql = "
      UPDATE
        gp_order_items
      SET
        days_dispensed_default  = $days,
        qty_dispensed_default   = ".($days*$item['sig_qty_per_day']).",
        item_message_key        = '$message_key',
        item_message_text       = '$message_text',
        price_dispensed_default = ".ceil($days*$price/30).",
        refills_total_default   = $item[refills_total],
        stock_level_initial     = '$item[stock_level]',
        refill_date_manual      = ".($item['refill_date_manual'] ? "'$item[refill_date_manual]'" : 'NULL').",
        refill_date_default     = ".($item['refill_date_default'] ? "'$item[refill_date_default]'" : 'NULL').",
        refill_date_last        = ".($item['refill_date_last'] ? "'$item[refill_date_last]'" : 'NULL')."
      WHERE
        invoice_number = $item[invoice_number] AND
        rx_number = $item[rx_number]
    ";

    $mysql->run($sql);
  }
  else {
    $sql = '';
    log_error('ERROR set_days_default. days_dispensed_default is set but days_dispensed_actual is not, so why is this function being called?', get_defined_vars());
  }
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

function sync_to_order_past_due($item) {
  return $item['refill_date_next'] AND (strtotime($item['refill_date_next']) - time()) < 0;
}

function sync_to_order_due_soon($item) {
  return $item['refill_date_next'] AND (strtotime($item['refill_date_next']) - time()) <= 15*24*60*60;
}

function days_left_in_rx($item, $days_std = 90) {

  if ($item['item_date_added']) return;

  $days_left_in_rx = round($item['qty_left']/$item['sig_qty_per_day']);

  //Fill up to 30 days more to finish up an Rx if almost finished.
  //E.g., If 30 day script with 3 refills (4 fills total, 120 days total) then we want to 1x 120 and not 1x 90 + 1x30
  if ($days_left_in_rx <= $days_std+30) return $days_left_in_rx;
}

function days_left_in_stock($item, $days_std = 90) {

  if ($item['item_date_added']) return;

  $days_left_in_stock = round($item['qty_inventory']/$item['sig_qty_per_day']);

  if ($days_left_in_stock < $days_std OR $item['qty_inventory'] < 500) {

    if($item['stock_level'] == STOCK_LEVEL['HIGH SUPPLY'])
      log_error('LOW STOCK ITEM IS MARKED HIGH SUPPLY', get_defined_vars());

    return $item['sig_qty_per_day'] == 1/30 ? 30 : 45;
  }
}

//Days is basically the MIN(target_date ?: std_day, qty_left as days, inventory_left as days).
//NOTE: We adjust bump up the days by upto 30 in order to finish up an Rx (we don't want partial fills left)
//NOTE: We base this on the best_rx_number and NOT on the rx currently in the order
function days_default($item, $days_std = 90) {

  //Convert qtys to days
  $days_left_in_rx    = days_left_in_rx($item, $days_std) ?: $days_std;
  $days_left_in_stock = days_left_in_stock($item, $days_std) ?: $days_std;

  $days_default = min($days_left_in_rx, $days_left_in_stock);

  if ($days_default % 15)
    log_error("DEFAULT DAYS IS NOT A MULTIPLE OF 15! days_default:$days_default, days_of_stock:$days_of_stock, days_of_qty_left:$days_of_qty_left, days_std:$days_std, refill_date_next:$item[refill_date_next].", get_defined_vars());
  else
    log_info("days_default:$days_default, days_left_in_stock:$days_left_in_stock, days_left_in_rx:$days_left_in_rx, days_std:$days_std, refill_date_next:$item[refill_date_next].", get_defined_vars());

  return $days_default;
}
