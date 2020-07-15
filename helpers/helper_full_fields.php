<?php

//Simplify GDoc Invoice Logic by combining _actual
function add_full_fields($patient_or_order, $mysql, $overwrite_rx_messages) {

  $count_filled = 0;

  //Consolidate default and actual suffixes to avoid conditional overload in the invoice template and redundant code within communications
  foreach($patient_or_order as $i => $dontuse) { //don't use val because order[$i] and $item will become out of sync as we set properties

    if ($patient_or_order[$i]['rx_message_key'] == 'ACTION NO REFILLS' AND @$patient_or_order[$i]['rx_dispensed_id'] AND $patient_or_order[$i]['refills_total'] >= .1) {
      log_error('add_full_fields: status of ACTION NO REFILLS but has refills. Do we need to send updated communications?', $patient_or_order[$i]);
      $patient_or_order[$i]['rx_message_key'] = NULL;
    }

    $days     = NULL;
    $message  = NULL;

    $patient_or_order[$i]['refills_used'] = +$patient_or_order[$i]['refills_used']; //Turn string into number so that "0.00" is falsey instead of truthy

    $patient_or_order[$i]['rx_date_written'] = date('Y-m-d', strtotime($patient_or_order[$i]['rx_date_expired'].' -1 year')); //Set before export_gd_transfer_fax()

    //If this is full_patient was don't JOIN the order_items/order tables so those fields will not be set here
    $overwrite = ($overwrite_rx_messages === true OR $overwrite_rx_messages == $patient_or_order[$i]['rx_number']);
    $set_days  = (@$patient_or_order[$i]['item_date_added'] AND is_null($patient_or_order[$i]['days_dispensed_default']));
    $set_msgs  = ($overwrite OR ! $patient_or_order[$i]['rx_message_key'] OR is_null($patient_or_order[$i]['rx_message_text']));

    if ($set_days OR $set_msgs) {
      list($days, $message) = get_days_default($patient_or_order[$i], $patient_or_order);

      if ($set_days AND is_null($days))
        log_error("helper_full_fields set_days: days should not be NULL", get_defined_vars());
      else if ($set_days)
        $patient_or_order[$i] = set_days_default($patient_or_order[$i], $days, $mysql);

      if ($set_msgs) { //On a sync_to_order the rx_message_key will be set, but days will not yet be set since their was not an order_item until now.  But we don't want to override the original sync message
        $patient_or_order[$i] = export_cp_set_rx_message($patient_or_order[$i], $message, $mysql);
        export_gd_transfer_fax($patient_or_order[$i], 'helper full fields'); //Internal logic determines if fax is necessary
      }

      //log_notice('add_full_fields: after', ['item' => $patient_or_order[$i]]);

      if ($patient_or_order[$i]['sig_days'] AND $patient_or_order[$i]['sig_days'] != 90) {
        log_notice("helper_full_order: sig has days specified other than 90", $patient_or_order[$i]);
      }

    }

    if ( ! $patient_or_order[$i]['rx_message_key'] OR is_null($patient_or_order[$i]['rx_message_text'])) {
      log_error('add_full_fields: error rx_message not set!', [
        'item' => $patient_or_order[$i],
        'days' => $days,
        'message' => $message,
        'set_days' => $set_days,
        'set_msgs' => $set_msgs,
        '! order[$i][rx_message_key] '       => ! $patient_or_order[$i]['rx_message_key'],
        'is_null(order[$i][rx_message_text]' => is_null($patient_or_order[$i]['rx_message_text'])
      ]);
    }

    //TODO consider making these methods so that they always stay upto date and we don't have to recalcuate them when things change
    $patient_or_order[$i]['drug'] = $patient_or_order[$i]['drug_name'] ?: $patient_or_order[$i]['drug_generic'];
    $patient_or_order[$i]['payment_method']  = @$patient_or_order[$i]['payment_method_actual'] ?: @$patient_or_order[$i]['payment_method_default'];

    if ($patient_or_order[$i]['payment_method'] != $patient_or_order[$i]['payment_method_default']) {
      log_error('add_full_fields: payment_method_actual is set but does not equal payment_method_default. Was coupon removed?', get_defined_vars());

      if ($patient_or_order[$i]['payment_method_actual'] == PAYMENT_METHOD['COUPON']) //Order 39025.  Ideally this would be removed since if we remove coupon from patient it should remove it from order as well
        $patient_or_order[$i]['payment_method'] = @$patient_or_order[$i]['payment_method_default'];
    }

    if ( ! isset($patient_or_order[$i]['invoice_number'])) continue; //The rest of the fields are order specific and will not be available if this is a patient

    $patient_or_order[$i]['days_dispensed'] = $patient_or_order[$i]['days_dispensed_actual'] ?: $patient_or_order[$i]['days_dispensed_default'];
    if ($patient_or_order[$i]['days_dispensed']) $count_filled++;

    if ( ! $count_filled AND ($patient_or_order[$i]['days_dispensed'] OR $patient_or_order[$i]['days_dispensed_default'] OR $patient_or_order[$i]['days_dispensed_actual'])) {
      log_error('add_full_fields: What going on here?', get_defined_vars());
    }

    //refills_dispensed_default/actual only exists as an order item.  But for grouping we need to know for items not in the order
    $patient_or_order[$i]['refills_dispensed'] = (float) ($patient_or_order[$i]['refills_dispensed_actual'] ?: ($patient_or_order[$i]['refills_dispensed_default'] ?: $patient_or_order[$i]['refills_total']));
    $patient_or_order[$i]['qty_dispensed']     = (float) ($patient_or_order[$i]['qty_dispensed_actual'] ?: $patient_or_order[$i]['qty_dispensed_default']); //cast to float to get rid of .000 decimal
    $patient_or_order[$i]['price_dispensed']   = (float) ($patient_or_order[$i]['price_dispensed_actual'] ?: ($patient_or_order[$i]['price_dispensed_default'] ?: 0));
  }

  foreach($patient_or_order as $i => $item)
    $patient_or_order[$i]['count_filled'] = $count_filled;

  return $patient_or_order;
}
