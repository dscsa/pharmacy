<?php

  //TODO Calculate Qty, Days, & Price

  function get_days_dispensed($order_item) {
    echo "get_days_dispensed ".print_r($order_item, true);
  }

  function set_days_dispensed($order_item) {
    echo "set_days_dispensed ".print_r($order_item, true);
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
    $mysql->run(sql('ACTION_EXPIRED', 'DATEDIFF(rx_date_expired, refill_date_next) < 0', 0, 0));

    //DON'T FILL MEDICATIONS WITHOUT REFILLS
    $mysql->run(sql('ACTION_NO_REFILLS', 'refill_total < 0.1'), 0, 0);

    //CAN'T FILL MEDICATIONS WITHOUT A GCN MATCH
    $mysql->run(sql('NOACTION_MISSING_GCN', 'drug_gsns IS NULL'), 0, 0);

    //TRANSFER OUT NEW RXS IF NOT OFFERED
    $mysql->run(sql('NOACTION_WILL_TRANSFER', 'refill_date_first IS NULL AND stock_level = "NOT OFFERED"'), 0, 0);

    //CHECK BACK IF TRANSFER OUT IS NOT DESIRED
    $mysql->run(sql('CASE WHEN (price_per_month >= 20 OR pharmacy_phone = "8889875187") THEN "ACTION_CHECK_BACK" ELSE "NOACTION_WILL_TRANSFER_CHECK_BACK" END', '
      refill_date_first IS NULL AND
      stock_level IN ("OUT OF STOCK","REFILLS ONLY")
    '), 0, 0);

    //CHECK BACK NOT ENOUGH QTY UNLESS ADDED MANUALLY
    //TODO MAYBE WE SHOULD JUST MOVE THE REFILL_DATE_NEXT BACK BY A WEEK OR TWO
    $mysql->run(sql('ACTION_CHECK_BACK', '
      refill_date_first IS NOT NULL AND
      qty_inventory / sig_qty_per_day < 30 AND
      item_added_by NOT IN ("MANUAL","WEBFORM")
    '), 0, 0);

    //DON'T FILL IF PATIENT AUTOFILL IS OFF AND NOT MANUALLY ADDED
    $mysql->run(sql('ACTION_PAT_OFF_AUTOFILL', '
      pat_autofill <> 1 AND
      item_added_by NOT IN ("MANUAL","WEBFORM")
    '), 0, 0);

    //DON'T REFILL IF FILLED WITHIN LAST 30 DAYS UNLESS ADDED MANUALLY
    $mysql->run(sql('NOACTION_RECENT_FILL', '
      DATEDIFF(item_date_added, refill_date_last) < 30 AND
      item_added_by NOT IN ("MANUAL","WEBFORM")
    '), 0, 0);

    //DON'T REFILL IF NOT DUE IN OVER 15 DAYS UNLESS ADDED MANUALLY
    $mysql->run(sql('NOACTION_NOT_DUE', '
      DATEDIFF(refill_date_next, item_date_added) > 15 AND
      item_added_by NOT IN ("MANUAL","WEBFORM")
    '), 0, 0);

    //SIG SEEMS TO HAVE EXCESSIVE QTY
    $mysql->run(sql('NOACTION_CHECK_SIG', '
      refill_date_first IS NULL AND
      qty_inventory < 2000 AND
      sig_qty_per_day > 2.5*qty_repack AND
      item_added_by NOT IN ("MANUAL","WEBFORM")
    '), 0, 0);

    //TODO NOACTION_LIVE_INVENTORY_ERROR if (drug.$IsRefill && drug.$Stock == 'Not Offered')

    //TODO DON'T FILL IF RX AUTOFILL IS OFF AND NOT IN ORDER

    //TODO NOACTION_RX_OFF_AUTOFILL  if( ! drug.$Autofill.patient && drug.$ManuallyAdded)

    //TODO NOACTION_RX_OFF_AUTOFILL  if( ! drug.$Autofill.rx && drug.$ManuallyAdded)

    //TODO DON'T NOACTION_PAST_DUE if ( ! drug.$InOrder && drug.$DaysToRefill < 0)

    //TODO ACTION_EXPIRING //if (timeToExpiry < drug.$Days*24*60*60*1000)

    //TODO NOACTION_LIVE_INVENTORY_ERROR if ( ! drug.$v2)

    //TODO ACTION_CHECK_BACK/NOACTION_WILL_TRANSFER_CHECK_BACK
    //if ( ! drug.$IsRefill && ! drug.$IsPended && ~ ['Out of Stock', 'Refills Only'].indexOf(drug.$Stock))
    //if (drug.$NoTransfer)

    //TODO ACTION_NEEDS_FORM if (drug.$Autofill.patient == null)
  }
