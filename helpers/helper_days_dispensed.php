<?php

//TODO Calculate Qty, Days, & Price

function get_days_dispensed($item) {

  echo "get_days_dispensed ".print_r($item, true);

  $no_transfer = $item['price_per_month'] >= 20 OR $item['pharmacy_phone'] == "8889875187";

  $manual = in_array($item['stock_level'], ["MANUAL","WEBFORM"]);

  $not_offered = $item['stock_level'] == STOCK_LEVEL['NOT OFFERED'];

  $refills_only = in_array($item['stock_level'], [
    STOCK_LEVEL['OUT OF STOCK'],
    STOCK_LEVEL['REFILLS ONLY']
  ]);

  if ($item['rx_date_expired'] < $item['refill_date_next']) {
    echo "DON'T FILL EXPIRED MEDICATIONS";
    return [0, RX_MESSAGE['ACTION_EXPIRED']];
  }

  if ($item['refills_left'] < 0.1) {
    echo "DON'T FILL MEDICATIONS WITHOUT REFILLS";
    return [0, RX_MESSAGE['ACTION_NO_REFILLS']];
  }

  if ( ! $item['drug_gsns']) {
    echo "CAN'T FILL MEDICATIONS WITHOUT A GCN MATCH";
    return [0, RX_MESSAGE['NOACTION_MISSING_GCN']];
  }

  if ( ! $item['refill_date_first'] AND $not_offered) {
    echo "TRANSFER OUT NEW RXS THAT WE DONT CARRY";
    return [0, RX_MESSAGE['NOACTION_WILL_TRANSFER']];
  }

  if ($no_transfer AND ! $item['refill_date_first'] AND $refills_only) {
    echo "CHECK BACK IF TRANSFER OUT IS NOT DESIRED";
    return [0, RX_MESSAGE['ACTION_CHECK_BACK']];
  }

  if ( ! $no_transfer AND ! $item['refill_date_first'] AND $refills_only) {
    echo "TRANSFER OUT NEW RXS THAT WE CANT FILL";
    return [0, RX_MESSAGE['NOACTION_WILL_TRANSFER_CHECK_BACK']];
  }

  //TODO MAYBE WE SHOULD JUST MOVE THE REFILL_DATE_NEXT BACK BY A WEEK OR TWO
  if ($item['refill_date_first'] AND ($item['qty_inventory']/$item['sig_qty_per_day'] < 30) AND ! $manual) {
    echo "CHECK BACK NOT ENOUGH QTY UNLESS ADDED MANUALLY";
    return [0, RX_MESSAGE['ACTION_CHECK_BACK']];
  }

  if (is_null($item['patient_autofill'])) {
    echo "PATIENT NEEDS TO REGISTER";
    return [0, RX_MESSAGE['ACTION_NEEDS_FORM']];
  }

  if ( ! $item['patient_autofill'] AND ! $manual) {
    echo "DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED";
    return [0, RX_MESSAGE['ACTION_PATIENT_OFF_AUTOFILL']];
  }

  if ( ! $item['patient_autofill'] AND $manual) {
    echo "OVERRIDE PATIENT AUTOFILL OFF SINCE MANUALLY ADDED";
    return [days($item), RX_MESSAGE['NOACTION_RX_OFF_AUTOFILL']];
  }

  if ((strtotime($item['item_date_added']) - strtotime($item['refill_date_last'])) < 30*24*60*60 AND ! $manual) {
    echo "DON'T REFILL IF FILLED WITHIN LAST 30 DAYS UNLESS ADDED MANUALLY";
    return [0, RX_MESSAGE['NOACTION_RECENT_FILL']];
  }

  if ((strtotime($item['refill_date_next']) - strtotime($item['item_date_added'])) > 15*24*60*60 AND ! $manual) {
    echo "DON'T REFILL IF NOT DUE IN OVER 15 DAYS UNLESS ADDED MANUALLY";
    return [0, RX_MESSAGE['NOACTION_NOT_DUE']];
  }

  if ( ! $item['refill_date_first'] AND $item['qty_inventory'] < 2000 AND ($item['sig_qty_per_day'] > 2.5*$item['qty_repack']) AND ! $manual) {
    echo "SIG SEEMS TO HAVE EXCESSIVE QTY";
    return [0, RX_MESSAGE['NOACTION_CHECK_SIG']];
  }

  if ( ! $item['refill_date_first'] AND $not_offered) {
    echo "REFILLS SHOULD NOT HAVE A NOT OFFERED STATUS";
    return [days($item), RX_MESSAGE['NOACTION_LIVE_INVENTORY_ERROR']];
  }

  if ( ! $item['rx_autofill']) { //InOrder is implied here
    echo "IF RX IS IN ORDER FILL IT EVEN IF RX_AUTOFILL IS OFF";
    return [days($item), RX_MESSAGE['NOACTION_RX_OFF_AUTOFILL']];
  }

  if ((strtotime($item['rx_date_expired']) - strtotime($item['refill_date_next'])) < 45*24*60*60) {
    echo "WARN USERS IF RX IS ABOUT TO EXPIRE";
    return [day($item), RX_MESSAGE['ACTION_EXPIRING']];
  }

  if ($item['stock_level'] != STOCK_LEVEL['HIGH SUPPLY'] && $item['qty_inventory'] < 1000) { //Only do 45 day if its Low Stock AND less than 1000 Qty.  Cindy noticed we had 8000 Amlodipine but we were filling in 45 day supplies
    echo "WARN USERS IF DRUG IS LOW QTY";
    return [day($item, 45), RX_MESSAGE['NOACTION_LOW_STOCK']];
  }

  return [days($item), ['EN' => '', 'ES' => '']];
  //TODO DON'T NOACTION_PAST_DUE if ( ! drug.$InOrder && drug.$DaysToRefill < 0)
  //TODO NOACTION_LIVE_INVENTORY_ERROR if ( ! drug.$v2)
  //TODO ACTION_CHECK_BACK/NOACTION_WILL_TRANSFER_CHECK_BACK
  //if ( ! drug.$IsRefill && ! drug.$IsPended && ~ ['Out of Stock', 'Refills Only'].indexOf(drug.$Stock))
  //if (drug.$NoTransfer)
}

function set_days_dispensed($item, $days, $status, $mysql) {

  if ( ! $item['days_dispensed_default']) {

    $sql = "
      UPDATE
        gp_order_items
      SET
        days_dispensed_default = $days,
        qty_dispensed_default  = $days*$item[sig_qty_per_day],
        refills_total_default  = $item[refills_total],
        item_status            = ".array_search($status, RX_MESSAGE)."
      WHERE
        rx_number = $item[rx_number]
    ";

    $mysql->run($sql);
  }
  else {

    $sql = "
      UPDATE
        gp_order_items
      SET
        -- days_dispensed_default = $days, -- Already set by CP table
        -- qty_dispensed_default  = $days*$item[sig_qty_per_day], -- Already set by CP table
        refills_total_actual = $item[refills_total]
      WHERE
        rx_number = $item[rx_number]
    ";

    $mysql->run($sql);
  }

  echo "set_days_dispensed days:$days, $sql".print_r($item, true);
}


//Days is basically the MIN(target_date ?: std_day, qty_left as days, inventory_left as days).
//NOTE: We adjust bump up the days by upto 30 in order to finish up an Rx (we don't want partial fills left)
//NOTE: We base this on the best_rx_number and NOT on the rx currently in the order
function days_default($item, $days_std = 90) {

  //Convert qtys to days
  $days_of_qty_left = round($item['qty_left']/$item['sig_qty_per_day']);
  $days_of_stock    = round($item['qty_inventory']/$item['sig_qty_per_day']);

  //Get to the target number of days
  if ($item['refill_date_target'])
    $days_std = (strtotime($item['refill_date_target']) - strtotime($item['refill_date_target']))/60/60/24;

  //Fill up to 30 days more to finish up an Rx if almost finished
  $days_default = ($days_of_qty_left < $days_std+30) ? $days_of_qty_left : $days_std;

  $days_default = min($days, $days_of_stock);

  mail('adam@sirum.org', "days()", "days:$days, days_of_stock:$days_of_stock, days_of_qty_left:$days_of_qty_left, days_std:$days_std, refill_date_target:$item[refill_date_target]. ".print_r($changes, true));

  return $days_default;
}
