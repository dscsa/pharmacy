<?php

//TODO Calculate Qty, Days, & Price

function get_days_dispensed($item) {

  log_info("
  get_days_dispensed ");//.print_r($item, true);

  $no_transfer = $item['price_per_month'] >= 20 OR $item['pharmacy_phone'] == "8889875187";

  $manual = in_array($item['stock_level'], ["MANUAL","WEBFORM"]);

  $not_offered = $item['stock_level'] == STOCK_LEVEL['NOT OFFERED'];

  $refills_only = in_array($item['stock_level'], [
    STOCK_LEVEL['OUT OF STOCK'],
    STOCK_LEVEL['REFILL ONLY']
  ]);

  if ($item['rx_date_expired'] < $item['refill_date_next']) {
    log_info("
    DON'T FILL EXPIRED MEDICATIONS");
    return [0, RX_MESSAGE['ACTION EXPIRED']];
  }

  if ($item['refills_left'] < 0.1) {
    log_info("
    DON'T FILL MEDICATIONS WITHOUT REFILLS");
    return [0, RX_MESSAGE['ACTION NO REFILLS']];
  }

  if ( ! $item['drug_gsns']) {
    log_info("
    CAN'T FILL MEDICATIONS WITHOUT A GCN MATCH");
    return [0, RX_MESSAGE['NOACTION MISSING GCN']];
  }

  if ( ! $item['refill_date_first'] AND $not_offered) {
    log_info("
    TRANSFER OUT NEW RXS THAT WE DONT CARRY");
    return [0, RX_MESSAGE['NOACTION WILL TRANSFER']];
  }

  if ($no_transfer AND ! $item['refill_date_first'] AND $refills_only) {
    log_info("
    CHECK BACK IF TRANSFER OUT IS NOT DESIRED");
    return [0, RX_MESSAGE['ACTION CHECK BACK']];
  }

  if ( ! $no_transfer AND ! $item['refill_date_first'] AND $refills_only) {
    log_info("
    TRANSFER OUT NEW RXS THAT WE CANT FILL");
    return [0, RX_MESSAGE['NOACTION WILL TRANSFER CHECK BACK']];
  }

  //TODO MAYBE WE SHOULD JUST MOVE THE REFILL_DATE_NEXT BACK BY A WEEK OR TWO
  if ($item['refill_date_first'] AND ($item['qty_inventory']/$item['sig_qty_per_day'] < 30) AND ! $manual) {
    log_info("
    CHECK BACK NOT ENOUGH QTY UNLESS ADDED MANUALLY");
    return [0, RX_MESSAGE['ACTION CHECK BACK']];
  }

  if (is_null($item['patient_autofill'])) {
    log_info("
    PATIENT NEEDS TO REGISTER");
    return [0, RX_MESSAGE['ACTION NEEDS FORM']];
  }

  if ( ! $item['patient_autofill'] AND ! $manual) {
    log_info("
    DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED");
    return [0, RX_MESSAGE['ACTION PATIENT OFF AUTOFILL']];
  }

  if ( ! $item['patient_autofill'] AND $manual) {
    log_info("
    OVERRIDE PATIENT AUTOFILL OFF SINCE MANUALLY ADDED");
    return [days_default($item), RX_MESSAGE['NOACTION RX OFF AUTOFILL']];
  }

  if ((strtotime($item['item_date_added']) - strtotime($item['refill_date_last'])) < 30*24*60*60 AND ! $manual) {
    log_info("
    DON'T REFILL IF FILLED WITHIN LAST 30 DAYS UNLESS ADDED MANUALLY");
    return [0, RX_MESSAGE['NOACTION RECENT FILL']];
  }

  if ((strtotime($item['refill_date_next']) - strtotime($item['item_date_added'])) > 15*24*60*60 AND ! $manual) {
    log_info("
    DON'T REFILL IF NOT DUE IN OVER 15 DAYS UNLESS ADDED MANUALLY");
    return [0, RX_MESSAGE['NOACTION NOT DUE']];
  }

  if ( ! $item['refill_date_first'] AND $item['qty_inventory'] < 2000 AND ($item['sig_qty_per_day'] > 2.5*$item['qty_repack']) AND ! $manual) {
    log_info("
    SIG SEEMS TO HAVE EXCESSIVE QTY");
    return [0, RX_MESSAGE['NOACTION CHECK SIG']];
  }

  if ( ! $item['refill_date_first'] AND $not_offered) {
    log_info("
    REFILLS SHOULD NOT HAVE A NOT OFFERED STATUS");
    return [days_default($item), RX_MESSAGE['NOACTION LIVE INVENTORY ERROR']];
  }

  if ( ! $item['rx_autofill']) { //InOrder is implied here
    log_info("
    IF RX IS IN ORDER FILL IT EVEN IF RX_AUTOFILL IS OFF");
    return [days_default($item), RX_MESSAGE['NOACTION RX OFF AUTOFILL']];
  }

  if ((strtotime($item['rx_date_expired']) - strtotime($item['refill_date_next'])) < 45*24*60*60) {
    log_info("
    WARN USERS IF RX IS ABOUT TO EXPIRE");
    return [days_default($item), RX_MESSAGE['ACTION EXPIRING']];
  }

  if ($item['stock_level'] != STOCK_LEVEL['HIGH SUPPLY'] && $item['qty_inventory'] < 1000) { //Only do 45 day if its Low Stock AND less than 1000 Qty.  Cindy noticed we had 8000 Amlodipine but we were filling in 45 day supplies
    log_info("
    WARN USERS IF DRUG IS LOW QTY");
    return [days_default($item, 45), RX_MESSAGE['NOACTION LOW STOCK']];
  }

  log_info("
  NO SPECIAL TAG USING DEFAULTS");
  return [days_default($item), RX_MESSAGE['NOACTION STANDARD FILL']];
  //TODO DON'T NOACTION_PAST_DUE if ( ! drug.$InOrder && drug.$DaysToRefill < 0)
  //TODO NOACTION_LIVE_INVENTORY_ERROR if ( ! drug.$v2)
  //TODO ACTION_CHECK_BACK/NOACTION_WILL_TRANSFER_CHECK_BACK
  //if ( ! drug.$IsRefill && ! drug.$IsPended && ~ ['Out of Stock', 'Refills Only'].indexOf(drug.$Stock))
  //if (drug.$NoTransfer)
}

function set_days_dispensed($item, $days, $message, $mysql) {

  if ( ! $item['rx_number'] OR ! $item['invoice_number'] ) {
    log_info("
    Error set_days_dispensed? ".print_r($item, true));
  }
  else if ( ! $item['days_dispensed_default']) {

    $message_key  = array_search($message, RX_MESSAGE);
    $message_text = $message[$item['language']];

    $price = $item['price_per_month'] ?: 0; //Might be null

    $sql = "
      UPDATE
        gp_order_items
      SET
        days_dispensed_default  = $days,
        qty_dispensed_default   = ".($days*$item['sig_qty_per_day']).",
        item_message_key        = '$message_key',
        item_message_text       = '$message_text',
        price_dispensed_default = ".($days*$price/30).",
        refills_total_default   = $item[refills_total]
      WHERE
        invoice_number = $item[invoice_number] AND
        rx_number = $item[rx_number]
    ";

    $mysql->run($sql);

    //log("
    //set_days_dispensed days:$days, $sql";//.print_r($item, true));

  }
  else {

    $sql = "
      UPDATE
        gp_order_items
      SET
        -- Other Fields Should Already Be Set Above (And May have Been Sent to Patient) so don't change
        price_dispensed_actual   = $days*$item[price_per_month]/30,
        refills_total_actual     = $item[refills_total]
      WHERE
        invoice_number = $item[invoice_number] AND
        rx_number = $item[rx_number]
    ";

    $mysql->run($sql);

    log_info("
    Actual? set_days_dispensed days:$days, $sql".print_r($item, true));
  }
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
    $days_std = (strtotime($item['refill_date_target']) - strtotime($item['refill_date_next']))/60/60/24;

  //Fill up to 30 days more to finish up an Rx if almost finished
  $days_default = ($days_of_qty_left < $days_std+30) ? $days_of_qty_left : $days_std;

  $days_default = min($days_default, $days_of_stock);

  $message = "
  days_default:$days_default, days_of_stock:$days_of_stock, days_of_qty_left:$days_of_qty_left, days_std:$days_std, refill_date_target:$item[refill_date_target], refill_date_next:$item[refill_date_next]. ";//.print_r($item, true);

  log_info($message);

  mail('adam@sirum.org', "days_default()", $message);

  return $days_default;
}
