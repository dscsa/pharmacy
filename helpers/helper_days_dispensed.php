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

  return [days($item), ['EN' => '', 'ES' => '']];
  //TODO DON'T NOACTION_PAST_DUE if ( ! drug.$InOrder && drug.$DaysToRefill < 0)
  //TODO NOACTION_LIVE_INVENTORY_ERROR if ( ! drug.$v2)
  //TODO ACTION_CHECK_BACK/NOACTION_WILL_TRANSFER_CHECK_BACK
  //if ( ! drug.$IsRefill && ! drug.$IsPended && ~ ['Out of Stock', 'Refills Only'].indexOf(drug.$Stock))
  //if (drug.$NoTransfer)
}

function set_days_dispensed($order_item) {
  echo "set_days_dispensed ".print_r($order_item, true);
}

function days($item) {

  $days_before_dispensed = Math.round($item.$RemainingQty/parsed.numDaily, 0)
  $days_limited_totalqty = $item.$IsPended ? Infinity : Math.round($item.$TotalQty/parsed.numDaily, 0)

  var stdDays = ($item.$Stock && $item.$TotalQty < 1000) ? 45 : 90 //Only do 45 day if its Low Stock AND less than 1000 Qty.  Cindy noticed we had 8000 Amlodipine but we were filling in 45 day supplies

  infoEmail('useEstimate', 'isLimited', days_limited_totalqty < Math.min(days_before_dispensed, stdDays), 'days_before_dispensed', days_before_dispensed, 'days_limited_totalqty', days_limited_totalqty, 'stdDays', stdDays, 'drug.$IsRefill', $item.$IsRefill, 'drug.$TotalQty', $item.$TotalQty, 'drug.$MonthlyPrice', $item.$MonthlyPrice, $item)

  if (days_limited_totalqty < Math.min(days_before_dispensed, stdDays)) {

    if ( ! $item.$NoTransfer) {
      set0Days($item)
      setDrugStatus($item, 'NOACTION_WILL_TRANSFER_CHECK_BACK')
      debugEmail('Low Quantity Transfer', parsed, 'days_before_dispensed', days_before_dispensed, 'days_limited_totalqty', days_limited_totalqty, 'stdDays', stdDays, 'drug.$IsRefill', $item.$IsRefill, 'drug.$TotalQty', $item.$TotalQty, 'drug.$MonthlyPrice', $item.$MonthlyPrice, $item)
      return
    }

    $item.$Days = days_limited_totalqty
    $item.$Type = "Estimate Limited Qty"
    setDrugStatus($item, 'NOACTION_LOW_STOCK')
    debugEmail('Low Quantity Hold', parsed, 'days_before_dispensed', days_before_dispensed, 'days_limited_totalqty', days_limited_totalqty, 'stdDays', stdDays, 'drug.$IsRefill', $item.$IsRefill, 'drug.$TotalQty', $item.$TotalQty, 'drug.$MonthlyPrice', $item.$MonthlyPrice, $item)
  }

  else if (days_before_dispensed <= stdDays+30) {
    $item.$Days = days_before_dispensed
    $item.$Type = "Estimate Finish Rx"
  }

  else {
    $item.$Days = stdDays
    $item.$Type = "Estimate Std Days"
  }

  $item.$Qty = +Math.min($item.$Days * parsed.numDaily, $item.$RemainingQty).toFixed(0) //Math.min added on 2019-01-02 because Order 9240 Promethizine had $Qty 42 > qty_before_dispensed Qty 40 because of rounding

  //This part is pulled from the CP_FillRx and CP_RefillRx SPs
  //See order #5307 - new script qty 90 w/ 1 refill dispensed as qty 45.  This basically switches the refills from 1 to 2, so after the 1st dispense there should still be one refill left
  var denominator = $item.$FirstRefill ? $item.$DispenseQty : $item.$WrittenQty  //DispenseQty will be pulled from previous Rxs.  We want to see if it has been set specifically for this Rx.
  setRefills($item, $item.$RefillsTotal - $item.$Qty/denominator)
}

function setRefills(drug, refills) {

  if (refills < .1) {
    refills = 0
    if ( ! drug.$Status) setDrugStatus(drug, 'ACTION_LAST_REFILL')
  }

  drug.$Refills = +refills.toFixed(2)
}


function sql($label, $where, $days, $qty) {
  return "
    UPDATE gp_order_items
    JOIN gp_rxs_grouped ON
      rx_numbers LIKE CONCAT('%,', rx_number, ',%')
    JOIN gp_stock_live ON
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
    JOIN gp_patients ON
      gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
    SET
      days_dispensed_default = $days,
      qty_dispensed_default  = $qty,
      item_status            = '$label'
    WHERE
      ($where) AND
      (days_dispensed_default IS NULL OR qty_dispensed_default IS NULL)
  ";
}

function old() {

  //DON'T FILL EXPIRED MEDICATIONS
  //$mysql->run(sql('ACTION_EXPIRED', 'DATEDIFF(rx_date_expired, refill_date_next) < 0', 0, 0));

  //DON'T FILL MEDICATIONS WITHOUT REFILLS
  //$mysql->run(sql('ACTION_NO_REFILLS', 'refill_total < 0.1'), 0, 0);

  //CAN'T FILL MEDICATIONS WITHOUT A GCN MATCH
  //$mysql->run(sql('NOACTION_MISSING_GCN', 'drug_gsns IS NULL'), 0, 0);

  //TRANSFER OUT NEW RXS IF NOT OFFERED
  //$mysql->run(sql('NOACTION_WILL_TRANSFER', 'refill_date_first IS NULL AND stock_level = "NOT OFFERED"'), 0, 0);

  //CHECK BACK IF TRANSFER OUT IS NOT DESIRED
  //$mysql->run(sql('CASE WHEN (price_per_month >= 20 OR pharmacy_phone = "8889875187") THEN "ACTION_CHECK_BACK" ELSE "NOACTION_WILL_TRANSFER_CHECK_BACK" END', '
  //  refill_date_first IS NULL AND
  //  stock_level IN ("OUT OF STOCK","REFILLS ONLY")
  //'), 0, 0);

  //CHECK BACK NOT ENOUGH QTY UNLESS ADDED MANUALLY
  //TODO MAYBE WE SHOULD JUST MOVE THE REFILL_DATE_NEXT BACK BY A WEEK OR TWO
  //$mysql->run(sql('ACTION_CHECK_BACK', '
  //  refill_date_first IS NOT NULL AND
  //  qty_inventory / sig_qty_per_day < 30 AND
  //  item_added_by NOT IN ("MANUAL","WEBFORM")
  //'), 0, 0);

  //DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED
  //$mysql->run(sql('ACTION_PAT_OFF_AUTOFILL', '
  //  pat_autofill <> 1 AND
  //  item_added_by NOT IN ("MANUAL","WEBFORM")
  //'), 0, 0);

  //DON'T REFILL IF FILLED WITHIN LAST 30 DAYS UNLESS ADDED MANUALLY
  //$mysql->run(sql('NOACTION_RECENT_FILL', '
  //  DATEDIFF(item_date_added, refill_date_last) < 30 AND
  //  item_added_by NOT IN ("MANUAL","WEBFORM")
  //'), 0, 0);

  //DON'T REFILL IF NOT DUE IN OVER 15 DAYS UNLESS ADDED MANUALLY
  //$mysql->run(sql('NOACTION_NOT_DUE', '
  //  DATEDIFF(refill_date_next, item_date_added) > 15 AND
  //  item_added_by NOT IN ("MANUAL","WEBFORM")
  //'), 0, 0);

  //SIG SEEMS TO HAVE EXCESSIVE QTY
  //$mysql->run(sql('NOACTION_CHECK_SIG', '
  //  refill_date_first IS NULL AND
  //  qty_inventory < 2000 AND
  //  sig_qty_per_day > 2.5*qty_repack AND
  //  item_added_by NOT IN ("MANUAL","WEBFORM")
  //'), 0, 0);


}
