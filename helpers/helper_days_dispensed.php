<?php
require_once 'helpers/helper_calendar.php';

use Sirum\Logging\SirumLog;

function get_days_default($item, $order) {

  $no_transfer    = is_no_transfer($item);
  $added_manually = is_added_manually($item);
  $not_offered    = is_not_offered($item);
  $is_refill      = is_refill($item, $order);
  $refill_only    = is_refill_only($item);
  $stock_level    = @$item['stock_level_initial'] ?: $item['stock_level'];

  $days_left_in_expiration = days_left_in_expiration($item);
  $days_left_in_refills    = days_left_in_refills($item);
  $days_left_in_stock      = days_left_in_stock($item);
  $days_default            = days_default($days_left_in_refills, $days_left_in_stock, DAYS_STD, $item);

  if ( ! $item['sig_qty_per_day_default'] AND $item['refills_original'] != $item['refills_left']) {
    log_error("helper_days_dispensed: RX WAS NEVER PARSED", $item);
  }

  if ($item['rx_date_transferred']) {

    if($stock_level == STOCK_LEVEL['HIGH SUPPLY'] AND strtotime($item['rx_date_transferred']) > strtotime('-2 day')) {

      $created = "Created:".date('Y-m-d H:i:s');

      $salesforce = [
        "subject"   => "$item[drug_name] was transferred recently although it's high stock",
        "body"      => "Investigate why drug $item[drug_name] for Rx $item[rx_number] was transferred out on $item[rx_date_transferred] even though it's high stock $created",
        "contact"   => "$item[first_name] $item[last_name] $item[birth_date]",
        "assign_to" => ".Transfer Out - RPh",
        "due_date"  => date('Y-m-d')
      ];

      $event_title = "$item[invoice_number] $item[drug_name] Is High Stock But Was Transferred: $salesforce[contact] $created";

      create_event($event_title, [$salesforce]);
      log_error($event_title, get_defined_vars());
    }

    else if($stock_level == STOCK_LEVEL['HIGH SUPPLY'])
      log_notice('HIGH STOCK ITEM WAS TRANSFERRED IN THE PAST', get_defined_vars());

    else
      log_info("RX WAS ALREADY TRANSFERRED OUT", get_defined_vars());

    return [0, RX_MESSAGE['NO ACTION WAS TRANSFERRED']];
  }

  if ($days_left_in_expiration < 0) { // Can't do <= 0 because null <= 0 is true
    log_info("DON'T FILL EXPIRED MEDICATIONS", get_defined_vars());
    return [0, RX_MESSAGE['ACTION EXPIRED']];
  }


  if ( ! $item['drug_gsns'] AND $item['drug_name']) {

    //Check for invoice number otherwise, seemed that SF tasks were being triplicated.  Unsure reason, maybe called by order_items and not just orders?
    if (@$item['invoice_number']) {
      $in_order = @$item['invoice_number'] ? "In Order #$item[invoice_number]," : "";
      $created = "Created:".date('Y-m-d H:i:s');

      if ($item['max_gsn']) {
        $body = "$in_order drug $item[drug_name] needs GSN $item[max_gsn] added to V2";
        $assign = "Joseph";
        log_error($body, $item);

      } else {
        $body = "$in_order drug $item[drug_name] needs to be switched to a drug with a GSN in Guardian";
        $assign = ".Delay/Expedite Order - RPh";
        log_notice($body, $item);
      }

      $salesforce = [
        "subject"   => "$in_order missing GSN for $item[drug_name]",
        "body"      => "$body $created",
        "contact"   => "$item[first_name] $item[last_name] $item[birth_date]",
        "assign_to" => $assign,
        "due_date"  => date('Y-m-d')
      ];

      $event_title = @$item['invoice_number']." Missing GSN: $salesforce[contact] $created";

      $mins_ago = (time() - strtotime(max($item['rx_date_changed'], $item['order_date_changed'], $item['item_date_added'])))/60;

      $mins_ago <= 30
        ? create_event($event_title, [$salesforce])
        : log_error("CONFIRM DIDN'T CREATE SALESFORCE TASK - DUPLICATE mins_ago:$mins_ago $event_title", [$item, $salesforce]);

      } else {
        log_error("CONFIRM DIDN'T CREATE SALESFORCE TASK - ORDER ITEMS NOT ORDER", $item);
      }

    return [ $item['refill_date_first'] ? $days_default : 0, RX_MESSAGE['NO ACTION MISSING GSN']];
  }

  if ( ! $is_refill AND $not_offered) {
    log_info("TRANSFER OUT NEW RXS THAT WE DONT CARRY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION WILL TRANSFER']];
  }

  if ($no_transfer AND ! $is_refill AND $refill_only) {
    log_info("CHECK BACK IF TRANSFER OUT IS NOT DESIRED", get_defined_vars());
    return [0, RX_MESSAGE['ACTION CHECK BACK']];
  }

  if ( ! $no_transfer AND ! $is_refill AND $refill_only) {
    log_info("TRANSFER OUT NEW RXS THAT WE CANT FILL", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION WILL TRANSFER CHECK BACK']];
  }

  if ( ! @$item['rx_dispensed_id'] AND $item['refills_total'] <= NO_REFILL) { //Unlike refills_dispensed_default/actual might not be set yet
    log_info("DON'T FILL MEDICATIONS WITHOUT REFILLS", $item);
    return [0, RX_MESSAGE['ACTION NO REFILLS']];
  }

  if ( ! $item['pharmacy_name']) {
    log_info("PATIENT NEEDS TO REGISTER", get_defined_vars());
    return [@$item['item_date_added'] ? $days_default : 0, RX_MESSAGE['ACTION NEEDS FORM']];
  }

  if ( ! $item['patient_autofill'] AND ! $added_manually) {
    log_info("DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED", get_defined_vars());
    return [0, RX_MESSAGE['ACTION PATIENT OFF AUTOFILL']];
  }

  if ( ! $item['patient_autofill'] AND $added_manually) {
    log_info("OVERRIDE PATIENT AUTOFILL OFF SINCE MANUALLY ADDED", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION PATIENT REQUESTED']];
  }

  if ((strtotime($item['refill_date_next']) - strtotime(@$item['order_date_added'])) > DAYS_UNIT*24*60*60 AND ! $added_manually) {

    //DON'T STRICTLY NEED THIS TEST BUT IT GIVES A MORE SPECIFIC ERROR SO IT MIGHT BE HELPFUL
    if ((strtotime(@$item['order_date_added']) - strtotime($item['refill_date_last'])) < DAYS_UNIT*24*60*60 AND ! $added_manually) {
      log_info("DON'T REFILL IF FILLED WITHIN LAST ".DAYS_UNIT." DAYS UNLESS ADDED MANUALLY", get_defined_vars());
      return [0, RX_MESSAGE['NO ACTION RECENT FILL']];
    }

    log_info("DON'T REFILL IF NOT DUE IN OVER ".DAYS_UNIT." DAYS UNLESS ADDED MANUALLY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION NOT DUE']];
  }

  /*
   * DAYS_UNIT has been replaced by hardcoded 28 at Pharmacy's request.  We
   * should evaluate if this needs to change to a constant.  Asana
   */
  if ((strtotime($item['refill_date_default']) - strtotime($item['refill_date_manual'])) > 28*24*60*60 AND $item['refill_date_manual'] AND ! $added_manually) {

    $created = "Created:".date('Y-m-d H:i:s');

    $salesforce = [
      "subject"   => "Investigate Early Refill",
      "body"      => "Confirm if/why needs $item[drug_name] in Order #$item[invoice_number] even though it's over "."28"." days before it's due. If needed, add drug to order. $created",
      "contact"   => "$item[first_name] $item[last_name] $item[birth_date]",
      "assign_to" => ".Add/Remove Drug - RPh",
      "due_date"  => date('Y-m-d')
    ];

    $event_title = "$item[invoice_number] $salesforce[subject]: $salesforce[contact] $created";

    create_event($event_title, [$salesforce]);
    return [0, RX_MESSAGE['NO ACTION NOT DUE']];
  }

  if ( ! $item['refill_date_first'] AND $item['last_inventory'] < 2000 AND ($item['sig_qty_per_day_default'] > 2.5*($item['qty_repack'] ?: 135)) AND ! $added_manually) {
    log_info("SIG SEEMS TO HAVE EXCESSIVE QTY", get_defined_vars());
    return [0, RX_MESSAGE['NO ACTION CHECK SIG']];
  }

  //If SureScript comes in AND only *rx* is off autofill, we assume patients wants it.
  //This is different when *patient* is off autofill, then we assume they don't want it unless added_manually
  if ( ! $item['rx_autofill'] AND ! @$item['item_date_added']) {
    log_info("DON'T FILL IF RX_AUTOFILL IS OFF AND NOT IN ORDER", get_defined_vars());
    return [0, RX_MESSAGE['ACTION RX OFF AUTOFILL']];
  }



  if ( ! $item['rx_autofill'] AND @$item['item_date_added']) {

    //39652 don't refill surescripts early if rx is off autofill.  This means refill_date_next is null but refill_date_default may have a value
    if ((strtotime($item['refill_date_default']) - strtotime(@$item['order_date_added'])) > DAYS_UNIT*24*60*60 AND ! $added_manually) {
      return [0, RX_MESSAGE['ACTION RX OFF AUTOFILL']];
    }

    log_info("OVERRIDE RX AUTOFILL OFF", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION RX REQUESTED']];
  }

  if ( ! $item['rx_autofill'] AND @$item['item_date_added']) {
    log_info("OVERRIDE RX AUTOFILL OFF", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION RX REQUESTED']];
  }

  if ($is_refill AND $not_offered) {
    log_info("REFILLS SHOULD NOT HAVE A NOT OFFERED STATUS", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION NEW GSN']];
  }

  if ( ! $added_manually AND sync_to_order_new_rx($item, $order)) {
    log_info('NO ACTION NEW RX SYNCED TO ORDER', get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION NEW RX SYNCED TO ORDER']];
  }

  //TODO and check if added by this program otherwise false positives
  if ( ! $added_manually AND sync_to_order_past_due($item, $order)) {
    log_info("WAS PAST DUE SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION PAST DUE AND SYNC TO ORDER']];
  }

  //TODO CHECK IF THIS IS A GUARDIAN ERROR OR WHETHER WE ARE IMPORTING WRONG.  SEEMS THAT IF REFILL_DATE_FIRST IS SET, THEN REFILL_DATE_DEFAULT should be set
  if ( ! $added_manually AND sync_to_order_no_next($item, $order)) {
    log_info("WAS MISSING REFILL_DATE_NEXT SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION NO NEXT AND SYNC TO ORDER']];
  }

  //TODO and check if added by this program otherwise false positives
  if ( ! $added_manually AND sync_to_order_due_soon($item, $order)) {
    log_info("WAS DUE SOON SO WAS SYNCED TO ORDER", get_defined_vars());
    return [$days_default, RX_MESSAGE['NO ACTION DUE SOON AND SYNC TO ORDER']];
  }

  if ($stock_level == STOCK_LEVEL['ONE TIME']) {
    return [$days_default, RX_MESSAGE['NO ACTION FILL ONE TIME']];
  }

  if ($stock_level == STOCK_LEVEL['OUT OF STOCK'] OR ($days_default AND $days_left_in_stock == $days_default)) {

    if ($item['last_inventory'] > 500) {

      log_error("helper_days_dispensed: LIKELY ERROR: 'out of stock' but inventory > 500", get_defined_vars());

    } else if ($is_refill) {

      $created = "Created:".date('Y-m-d H:i:s');

      $salesforce = [
        "subject"   => "Refill for $item[drug_name] seems to be out-of-stock",
        "body"      => "Refill for $item[drug_generic] $item[drug_gsns] ($item[drug_name]) in Order #$item[invoice_number] seems to be out-of-stock.  Is a substitution or purchase necessary? Details - days_left_in_stock:$days_left_in_stock, last_inventory:$item[last_inventory], sig:$item[sig_actual], $created",
        "contact"   => "$item[first_name] $item[last_name] $item[birth_date]",
        "assign_to" => "Joseph",
        "due_date"  => date('Y-m-d')
      ];

      $event_title = "$item[invoice_number] Refill Out Of Stock: $salesforce[contact] $created";

      if (stripos($item['first_name'], 'TEST') === FALSE AND stripos($item['last_name'], 'TEST') === FALSE)
        create_event($event_title, [$salesforce]);
    }
    else
      log_notice("WARN USERS IF DRUG IS LOW QTY", get_defined_vars());

    return [$days_default, RX_MESSAGE['NO ACTION FILL OUT OF STOCK']];
  }

  if ($days_left_in_refills AND $days_left_in_refills <= DAYS_MAX) {
    log_notice("$days_left_in_refills < ".DAYS_MAX." OF DAYS LEFT IN REFILLS", get_defined_vars());
    return [$days_left_in_refills, RX_MESSAGE['ACTION LAST REFILL']];
  }

  //Since last refill check already ran, this means we have more days left in refill that we have in the expiration
  //to maximize the amount dispensed we dispense until 10 days before the expiration and then as much as we can for the last refill
  if ($days_left_in_expiration AND $days_left_in_expiration < DAYS_MIN) {

    $days_left_of_qty = $item['qty_left']/$item['sig_qty_per_day']; //Cap it at 180 days
    $days_left_of_qty_capped = min(180, $days_left_of_qty);

    log_error("RX IS ABOUT TO EXPIRE SO FILL IT FOR EVERYTHING LEFT", get_defined_vars());
    return [$days_left_of_qty_capped, RX_MESSAGE['ACTION EXPIRING']];
  }

  if ($days_left_in_expiration AND $days_left_in_expiration < DAYS_STD) {

    $days_left_in_exp_rounded = roundDaysUnit($days_left_in_expiration);
    $days_left_in_exp_rounded_buffered = $days_left_in_exp_rounded-10;

    log_error("RX WILL EXPIRE SOON SO FILL IT UNTIL RIGHT BEFORE EXPIRATION DATE", get_defined_vars());
    return [$days_left_in_exp_rounded_buffered, RX_MESSAGE['ACTION EXPIRING']];
  }

  if ($stock_level == STOCK_LEVEL['REFILL ONLY']) {
    return [$days_default, RX_MESSAGE['NO ACTION FILL REFILL ONLY']];
  }

  if ($stock_level == STOCK_LEVEL['LOW SUPPLY']) {
    return [$days_default, RX_MESSAGE['NO ACTION FILL LOW SUPPLY']];
  }

  if ($stock_level == STOCK_LEVEL['HIGH SUPPLY']) {
    return [$days_default, RX_MESSAGE['NO ACTION FILL HIGH SUPPLY']];
  }

  log_info("NO SPECIAL RX_MESSAGE USING DEFAULTS", get_defined_vars());
  return [$days_default, RX_MESSAGE['NO ACTION FILL UNKNOWN']];
  //TODO DON'T NO ACTION_PAST_DUE if ( ! drug.$InOrder AND drug.$DaysToRefill < 0)
  //TODO NO ACTION_LIVE_INVENTORY_ERROR if ( ! drug.$v2)
  //TODO ACTION_CHECK_BACK/NO ACTION_WILL_TRANSFER_CHECK_BACK
  //if ( ! drug.$IsRefill AND ! drug.$IsPended AND ~ ['Out of Stock', 'Refills Only'].indexOf(drug.$Stock))
  //if (drug.$NoTransfer)
}

function freeze_invoice_data($item, $mysql) {

  if ( ! $item['days_dispensed_actual'])
    return log_error("freeze_invoice_data has no actual days", get_defined_vars());

  $price_per_month = $item['price_per_month'] ?: 0; //Might be null
  $price_actual    = ceil($item['days_dispensed_actual']*$price_per_month/30);

  if ($price_actual > 80)
    return log_error("freeze_invoice_data: price too high, $$price_actual", get_defined_vars());

  $sql = "
    UPDATE
      gp_order_items
    SET
      -- Other Fields Should Already Be Set Above (And May have Been Sent to Patient) so don't change
      price_dispensed_actual   = $price_actual,
      refills_dispensed_actual = $item[refills_total],
      item_message_keys        = '$item[rx_message_keys]',
      item_message_text        = '".escape_db_values($item['rx_message_text'])."'
    WHERE
      invoice_number = $item[invoice_number] AND
      rx_number = $item[rx_number]
  ";

  $mysql->run($sql);
}

function set_days_default($item, $days, $mysql) {

  //We can only save days if its currently an order_item
  if ( ! @$item['item_date_added'])
    return $item;

  if ($item['days_dispensed_actual']) {
    log_notice("set_days_default but it has actual days", get_defined_vars());
    return $item;
  }

  if ( ! $item['rx_number'] OR ! $item['invoice_number']) {
    log_error("set_days_default without a rx_number AND invoice_number ", get_defined_vars());
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
  $item['qty_dispensed_default']     = $days*$item['sig_qty_per_day_default'];
  $item['price_dispensed_default']   = ceil($days*$price/30);
  $item['refills_dispensed_default'] = refills_dispensed_default($item);  //We want invoice to show refills after they are dispensed assuming we dispense items currently in order
  $item['stock_level_initial']       = $item['stock_level'];

  if ($item['days_dispensed_default'] AND ! $item['qty_dispensed_default'])
    log_error('helper_days_dispensed: qty_dispensed_default is 0 but days_dispensed_default > 0', $item);

  $sql = "
    UPDATE
      gp_order_items
    SET
      days_dispensed_default    = ".($days ?: 0).",
      qty_dispensed_default     = $item[qty_dispensed_default],
      price_dispensed_default   = $item[price_dispensed_default],

      stock_level_initial       = '$item[stock_level_initial]',
      rx_message_keys_initial   = '$item[rx_message_keys]',

      zscore_initial            = ".(is_null($item['zscore']) ? 'NULL' : $item['zscore']).",
      patient_autofill_initial  = ".(is_null($item['patient_autofill']) ? 'NULL' : $item['patient_autofill']).",
      rx_autofill_initial       = '$item[rx_autofill]',
      rx_numbers_initial        = '$item[rx_numbers]',

      refills_dispensed_default = ".(is_null($item['refills_dispensed_default']) ? 'NULL' : $item['refills_dispensed_default']).",
      refill_date_manual        = ".($item['refill_date_manual'] ? "'$item[refill_date_manual]'" : 'NULL').",
      refill_date_default       = ".($item['refill_date_default'] ? "'$item[refill_date_default]'" : 'NULL').",
      refill_date_last          = ".($item['refill_date_last'] ? "'$item[refill_date_last]'" : 'NULL').",
      refill_target_date        = ".($item['rx_message_key'] == 'ACTION EXPIRING' ? "'$item[rx_date_expired]'" : 'NULL').",
      refill_target_days        = ".($item['rx_message_key'] == 'ACTION EXPIRING' ? $days : 'NULL')."
    WHERE
      invoice_number = $item[invoice_number] AND
      rx_number = $item[rx_number]
  ";

  $mysql->run($sql);

  return $item;
}

function refills_dispensed_default($item) {

  if ($item['qty_total'] <= 0) //Not sure if decimal 0.00 evaluates to falsey in PHP
    return 0;

  if ($item['refill_date_first']) //This is initially called before days_dispensed_default is set, so assume a refill with null days is going to be filled (so subtract 1 from current refills)
    return max(0, $item['refills_total'] - ($item['days_dispensed_default'] === 0 ? 0 : 1));

  //6028507 if Cindy hasn't adjusted the days/qty yet we need to calculate it ourselves
  if ( ! is_null($item['qty_dispensed_default']))
    return $item['refills_total'] * (1 - $item['qty_dispensed_default']/$item['qty_total']);

  //No much info to go on.  We could throw an error or just go based on whether the drug is in the order or not
  log_error("CANNOT ASSESS refills_dispensed_default AT THIS POINT", $item);
  return $item['refills_total'] - ($item['item_date_added'] ? 1 : 0);
}

//TODO OR IT'S AN OTC
function is_no_transfer($item) {
  return $item['price_per_month'] >= 20 OR $item['pharmacy_phone'] == "8889875187";
}

function is_added_manually($item) {
  return in_array(@$item['item_added_by'], ADDED_MANUALLY);
}

function is_not_offered($item) {
  $stock_level = @$item['stock_level_initial'] ?: $item['stock_level'];

  $not_offered = (is_null($stock_level) OR ($stock_level == STOCK_LEVEL['NOT OFFERED']) OR ($stock_level == STOCK_LEVEL['ORDER DRUG']));

  if ($not_offered) //TODO Alert here is drug is not offered but has a last_inventory > 500
    log_notice('is_not_offered: true', [$item, $not_offered, "$stock_level == ".STOCK_LEVEL['NOT OFFERED']]);
  //else
  //  log_notice('is_not_offered: false', [get_defined_vars(), "$stock_level == ".STOCK_LEVEL['NOT OFFERED']]);

  return $not_offered;
}

//rxs_grouped includes drug name AND sig_qty_per_day_default.  If someone starts on Lipitor 20mg 1 time per day
//and then moves to Lipitor 20mg 2 times per day, we still want to honor this Rx as a refill rather than
//tell them it is out of stock just because the sig changed
function is_refill($item1, $order) {

  $refill_date_first = null;
  foreach ($order as $item2) {
    if ($item1['drug_generic'] == $item2['drug_generic'])
      $refill_date_first = $refill_date_first ?: $item2['refill_date_first'];
  }

  return !!$refill_date_first;
}

function is_refill_only($item) {
  return in_array(@$item['stock_level_initial'] ?: $item['stock_level'], [
    STOCK_LEVEL['OUT OF STOCK'],
    STOCK_LEVEL['REFILL ONLY']
  ]);
}

function message_text($message, $item) {
  return str_replace(array_keys($item), array_values($item), $message[$item['language']]);
}

function sync_to_order_new_rx($item, $order) {
  $not_offered  = is_not_offered($item);
  $refill_only  = is_refill_only($item);
  $is_refill    = is_refill($item, $order);
  $has_refills  = ($item['refills_total'] > NO_REFILL);
  $eligible     = (! @$item['item_date_added'] AND $has_refills AND ! $is_refill AND $item['rx_autofill'] AND ! $not_offered AND ! $refill_only);

  $vars = get_defined_vars();

  SirumLog::debug(
      "sync_to_order_new_rx",
      [
          'vars' => $vars
      ]
  );

  return $eligible AND ! is_duplicate_gsn($item, $order);
}

function sync_to_order_past_due($item, $order) {
  $eligible = (! @$item['item_date_added'] AND ($item['refills_total'] > NO_REFILL) AND $item['refill_date_next'] AND (strtotime($item['refill_date_next']) - strtotime($item['order_date_added'])) < 0);
  return $eligible AND ! is_duplicate_gsn($item, $order);
}

//Order 29017 had a refill_date_first and rx/pat_autofill ON but was missing a refill_date_default/refill_date_manual/refill_date_next
function sync_to_order_no_next($item, $order) {
  $eligible = (! @$item['item_date_added'] AND ($item['refills_total'] > NO_REFILL) AND is_refill($item, $order) AND ! $item['refill_date_next']);
  return $eligible AND ! is_duplicate_gsn($item, $order);
}

function sync_to_order_due_soon($item, $order) {
  $eligible = (! @$item['item_date_added'] AND ($item['refills_total'] > NO_REFILL) AND $item['refill_date_next'] AND (strtotime($item['refill_date_next'])  - strtotime($item['order_date_added'])) <= DAYS_UNIT*24*60*60);
  return $eligible AND ! is_duplicate_gsn($item, $order);
}

//Although you can dispense up until an Rx expires (so refill_date_next is well past rx_date_expired) we want to use
//as much of a Rx as possible, so if it will expire before  the standard dispense date than dispense everything left
function days_left_in_expiration($item) {

  //Usually don't like using time() because it can change, but in this case once it is marked as expired it will always be expired so there is no variability
  $comparison_date = $item['refill_date_next'] ? strtotime($item['refill_date_next']) : time();

  $days_left_in_expiration = (strtotime($item['rx_date_expired']) - $comparison_date)/60/60/24;

  //#29005 was expired but never dispensed, so check "refill_date_first" so we asking doctors for new rxs that we never dispensed
  if ($item['refill_date_first']) return $days_left_in_expiration;
}

function days_left_in_refills($item) {

  if ( ! (float) $item['sig_qty_per_day'] OR $item['sig_qty_per_day'] > 10)
    return;

  //Uncomment the line below if we are okay dispensign 2 bottles/rxs.  For now, we will just fill the most we can do with one Rx.
  //if ($item['refills_total'] != $item['refills_left']) return; //Just because we are out of refills on this script doesn't mean there isn't another script with refills

  $days_left_in_refills = $item['qty_left']/$item['sig_qty_per_day'];

  //Fill up to 30 days more to finish up an Rx if almost finished.
  //E.g., If 30 day script with 3 refills (4 fills total, 120 days total) then we want to 1x 120 and not 1x 90 + 1x30
  if ($days_left_in_refills <= DAYS_MAX) return roundDaysUnit($days_left_in_refills);
}

function days_left_in_stock($item) {

  if ( ! (float) $item['sig_qty_per_day'] OR $item['sig_qty_per_day'] > 10)
    return;

  $days_left_in_stock = round($item['last_inventory']/$item['sig_qty_per_day']);
  $stock_level = @$item['stock_level_initial'] ?: $item['stock_level'];

  if ($days_left_in_stock >= DAYS_STD OR $item['last_inventory'] >= 3*$item['qty_repack'])
    return;

  if($stock_level == STOCK_LEVEL['HIGH SUPPLY'] AND $item['sig_qty_per_day_default'] != round(1/30, 3))
    log_error("LOW STOCK ITEM IS MARKED HIGH SUPPLY $item[drug_generic] days_left_in_stock:$days_left_in_stock last_inventory:$item[last_inventory]", get_defined_vars());

  return $item['sig_qty_per_day_default'] == round(1/30, 3) ? 60.6 : DAYS_MIN; //Dispensed 2 inhalers per time, since 1/30 is rounded to 3 decimals (.033), 2 month/.033 = 60.6 qty
}

function roundDaysUnit($days) {
  //+.1 because we had 18qty with .201 sig_qty_per_day_default which gave floor(5.97) -> 75 days instead of 90
  //Bactrim with 6 qty and 2.0 sig_qty_per_day_default which gave floor(6/2/15) -> 0 days
  return $days < DAYS_UNIT ? $days : floor($days/DAYS_UNIT+.1)*DAYS_UNIT; //+.1 because we had 18qty with .201 sig_qty_per_day_default which gave floor(5.97) -> 75 days instead of 90
}

//Days is basically the MIN(target_date ?: std_day, qty_left as days, inventory_left as days).
//NOTE: We adjust bump up the days by upto 30 in order to finish up an Rx (we don't want partial fills left)
//NOTE: We base this on the best_rx_number and NOT on the rx currently in the order
function days_default($days_left_in_refills, $days_left_in_stock, $days_default, $item) {

  //Cannot have NULL inside of MIN()
  $days = min(
    $days_left_in_refills ?: $days_default,
    $days_left_in_stock ?: $days_default
  );

  $remainder = $days % DAYS_UNIT;

  if ($remainder == 5)
    log_notice("DEFAULT DAYS IS NOT A MULTIPLE OF ".DAYS_UNIT."! LIKELY BECAUSE RX EXPIRING days:$days, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());
  else if ($remainder)
    log_error("DEFAULT DAYS IS NOT A MULTIPLE OF ".DAYS_UNIT."! days:$days, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());
  else
    log_info("days:$days, days_left_in_stock:$days_left_in_stock, days_left_in_refills:$days_left_in_refills", get_defined_vars());

  return $days;
}

//Don't sync if an order with these instructions already exists in order
function is_duplicate_gsn($item1, $order) {
  //Don't sync if an order with these instructions already exists in order
  foreach($order as $item2) {
    if ($item1 !== $item2 AND @$item2['item_date_added'] AND $item1['drug_gsns'] == $item2['drug_gsns']) {
      log_notice("helper_days_dispensed syncing item: matching drug_gsns so did not SYNC TO ORDER' $item1[invoice_number] $item1[drug_name] $item1[rx_message_key] refills last:$item1[refill_date_last] next:$item1[refill_date_next] total:$item1[refills_total] left:$item1[refills_left]", ['item1' => $item1, 'item2' => $item2]);
      return true;
    }
  }

  return false;
}
