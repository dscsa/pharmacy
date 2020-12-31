<?php

use Sirum\Logging\SirumLog;

//Simplify GDoc Invoice Logic by combining _actual
function add_full_fields($patient_or_order, $mysql, $overwrite_rx_messages)
{
    $count_filled    = 0;
    $items_to_add    = [];
    $items_to_remove = [];
    $update_payment  = is_null(@$patient_or_order[0]['payment_total_default']);

    /*
     * Consolidate default and actual suffixes to avoid conditional overload in
     * the invoice template and redundant code within communications
     *
     * Don't use val because order[$i] and $item will become out of
     * sync as we set properties
     */
    foreach ($patient_or_order as $i => $dontuse) {

        if ( ! $patient_or_order[$i]['drug_name']) {
          log_notice("helper_full_fields: skipping item/rx because no drug name. likely an empty order", ['patient_or_order' => $patient_or_order]);
          continue;
        }

        if ($patient_or_order[$i]['rx_message_key'] == 'ACTION NO REFILLS'
                and @$patient_or_order[$i]['rx_dispensed_id']
                and $patient_or_order[$i]['refills_total'] >= .1) {
            log_error(
                'add_full_fields: status of ACTION NO REFILLS but has refills. ' .
                'Do we need to send updated communications?',
                $patient_or_order[$i]
            );
            $patient_or_order[$i]['rx_message_key'] = null;
        }

        $days     = null;
        $message  = null;

        //Turn string into number so that "0.00" is falsey instead of truthy
        $patient_or_order[$i]['refills_used'] = +$patient_or_order[$i]['refills_used'];

        //Set before export_gd_transfer_fax()
        $patient_or_order[$i]['rx_date_written'] = date(
            'Y-m-d',
            strtotime($patient_or_order[$i]['rx_date_expired'] . ' -1 year')
        );

        //Overwrite refers to the rx_single and rx_grouped table not the order_items table which deliberitely keeps its initial values
        $overwrite = ($overwrite_rx_messages === true
                            or strpos($patient_or_order[$i]['rx_numbers'], $overwrite_rx_messages) !== false);

        $set_days_and_msgs  = (
          ! $patient_or_order[$i]['rx_message_key']
          or is_null($patient_or_order[$i]['rx_message_text'])
          or (
            @$patient_or_order[$i]['item_date_added']
            and is_null($patient_or_order[$i]['days_dispensed_default'])
          )
        );

        $log_suffix = @$patient_or_order[$i]['invoice_number'].' '.$patient_or_order[$i]['first_name'].' '.$patient_or_order[$i]['last_name'].' '.$patient_or_order[$i]['drug_generic'];

        SirumLog::notice(
          "add_full_fields $log_suffix",
          [
            "set_days_and_msgs"      => $set_days_and_msgs,
            "overwrite"              => $overwrite,
            "overwrite_rx_messages"  => $overwrite_rx_messages,
            "rx_number"              => $patient_or_order[$i]['rx_number'],
            "patient_or_order[i]"    => $patient_or_order[$i]
          ]
        );

        if ($set_days_and_msgs or $overwrite) {

            list($days, $message) = get_days_and_message($patient_or_order[$i], $patient_or_order);

            //If days_actual are set, then $days will be 0 (because it will be a recent fill)
            $days_new_item   = ($days AND ! @$patient_or_order[$i]['days_dispensed_default']);
            $days_changed    = (@$patient_or_order[$i]['days_dispensed_default'] AND ! @$patient_or_order[$i]['days_dispensed_actual'] AND @$patient_or_order[$i]['days_dispensed_default'] != $days);

            $needs_adding    = ( ! @$patient_or_order[$i]['item_date_added'] AND $days > 0);
            $needs_removing  = (@$patient_or_order[$i]['item_date_added'] AND $days == 0);
            $needs_pending   = (@$patient_or_order[$i]['item_date_added'] AND $days > 0 AND ! @$patient_or_order[$i]['count_pended_total']);
            $needs_unpending = (@$patient_or_order[$i]['item_date_added'] AND $days == 0 AND @$patient_or_order[$i]['count_pended_total']);
            $needs_repending = (
              @$patient_or_order[$i]['item_date_added']
              AND $days
              AND @$patient_or_order[$i]['days_dispensed_default']
              AND $days != @$patient_or_order[$i]['days_dispensed_default']
              AND ! @$patient_or_order[$i]['sync_to_date_days_before']
            );

            $get_days_and_message = [
              "overwrite_rx_messages"      => $overwrite_rx_messages,
              "rx_number"                  => $patient_or_order[$i]['rx_number'],

              "new_days_dispensed_default" => $days,
              "old_days_dispensed_default" => @$patient_or_order[$i]['days_dispensed_default'], //Applicable for order but not for patient

              "new_rx_message_text"        => "$message[EN] ($message[CP_CODE])",
              "old_rx_message_text"        => $patient_or_order[$i]['rx_message_text'],

              "patient_or_order"           => $patient_or_order[$i],
              'needs_adding'               => $needs_adding,
              'needs_removing'             => $needs_removing,
              "needs_pending"              => $needs_pending,
              "needs_unpending"            => $needs_unpending,
              "needs_repending"            => $needs_repending,
              "days_changed"               => $days_changed,
              "sync_to_date_days_before"   => @$patient_or_order[$i]['sync_to_date_days_before']
            ];

            SirumLog::notice("get_days_and_message $log_suffix", $get_days_and_message);

            //Internal logic keeps initial values on order_items if they exist (don't want to contradict patient comms)
            $patient_or_order[$i] = set_days_and_message($patient_or_order[$i], $days, $message, $mysql);

            export_cp_set_rx_message($patient_or_order[$i], $message);

            if ($needs_removing) {

              if (@$patient_or_order[0]['invoice_number']) {
                $items_to_remove[] = $patient_or_order[$i];
              } else {
                SirumLog::alert("Item needs to be removed but NO Order?", [
                  'days'    => $days,
                  'message' => $message,
                  'item'    => $patient_or_order[$i]
                ]);
              }

              SirumLog::notice(
                "helper_full_fields: needs_removing (export_cp_remove_items) ".$patient_or_order[$i]['drug_name'],
                [
                  'item'    => $patient_or_order[$i],
                  'items_to_remove' => $items_to_remove,
                  'days'    => $days,
                  'message' => $message
                ]
              );
            }

            if ($needs_adding) {

              if (@$patient_or_order[0]['invoice_number']) {
                $items_to_add[] = $patient_or_order[$i];
              } else {
                SirumLog::alert("Item needs to be added but NO Order?", [
                  'days'    => $days,
                  'message' => $message,
                  'item'    => $patient_or_order[$i]
                ]);
              }

              SirumLog::notice(
                "helper_full_fields: needs_adding (export_cp_add_items) ".$patient_or_order[$i]['drug_name'],
                [
                  'item'         => $patient_or_order[$i],
                  'items_to_add' => $items_to_add,
                  'days'         => $days,
                  'message'      => $message
                ]
              );
            }

            if($needs_pending) {
              v2_pend_item($patient_or_order[$i], $mysql);
            }

            if($needs_unpending) {
              v2_unpend_item($patient_or_order[$i], $mysql);
            }

            if ($needs_repending) {
              v2_unpend_item($patient_or_order[$i], $mysql);
              v2_pend_item($patient_or_order[$i], $mysql);
            }

            if ($days_new_item OR $days_changed)
              $update_payment = true;

            //Internal logic determines if fax is necessary
            if ($set_days_and_msgs) //Sending because of overwrite may cause multiple faxes for same item
              export_gd_transfer_fax($patient_or_order[$i], 'helper full fields');

            if ($patient_or_order[$i]['sig_days'] and $patient_or_order[$i]['sig_days'] != 90) {
              log_notice("helper_full_order: sig has days specified other than 90", $patient_or_order[$i]);
            }
        }

        if ( ! $patient_or_order[$i]['rx_message_key'] or is_null($patient_or_order[$i]['rx_message_text'])) {
          log_error(
            "add_full_fields: error rx_message not set! $log_suffix",
            [
              'item' => $patient_or_order[$i],
              'days' => $days,
              'message' => $message,
              'set_days_and_msgs' => $set_days_and_msgs,
              '! order[$i][rx_message_key] '       => ! $patient_or_order[$i]['rx_message_key'],
              'is_null(order[$i][rx_message_text]' => is_null($patient_or_order[$i]['rx_message_text'])
            ]
          );
        }

        //TODO consider making these methods so that they always stay upto
        //TODO date and we don't have to recalcuate them when things change
        $patient_or_order[$i]['drug'] = $patient_or_order[$i]['drug_generic'];
        if ($patient_or_order[$i]['drug_name']) {
          $patient_or_order[$i]['drug'] = $patient_or_order[$i]['drug_name'];
        }

        $patient_or_order[$i]['payment_method'] = @$patient_or_order[$i]['payment_method_default'];
        if (@$patient_or_order[$i]['payment_method_actual']) {
          $patient_or_order[$i]['payment_method']  = @$patient_or_order[$i]['payment_method_actual'];
        }


        if (
            $i == 0 //Same for every item in order
            AND $patient_or_order[$i]['payment_method'] != $patient_or_order[$i]['payment_method_default']
            AND $patient_or_order[$i]['payment_method_default'] != PAYMENT_METHOD['CARD EXPIRED']
        ) {
          log_error(
            'add_full_fields: payment_method_actual ('.$patient_or_order[$i]['payment_method'].') is set but does not equal '.
            'payment_method_default ('.$patient_or_order[$i]['payment_method_default'].'). Did customer click on wrong payment type? Was coupon removed?',
            get_defined_vars()
          );

          /*
           * Order 39025.  Ideally this would be removed since if we remove
           * coupon from patient it should remove it from order as well
           */
          if ($patient_or_order[$i]['payment_method_actual'] == PAYMENT_METHOD['COUPON']) {
            $patient_or_order[$i]['payment_method'] = @$patient_or_order[$i]['payment_method_default'];
          }
        }

        if (! isset($patient_or_order[$i]['order_date_added'])) {
            /*
             * The rest of the fields are order specific and will not be
             * available if this is a patient
             */
            continue;
        }

        if ($patient_or_order[$i]['days_dispensed_actual']) {
          $days_dispensed = $patient_or_order[$i]['days_dispensed_actual'];

          $price_per_month = $patient_or_order[$i]['price_per_month'] ?: 0; //Might be null
          $price_dispensed = $patient_or_order[$i]['price_dispensed_actual'] = ceil($days_dispensed*$price_per_month/30);

          if ($price_dispensed > 80)
            log_error("helper_full_fields: price too high, $$price_dispensed", get_defined_vars());

        } else {
          $days_dispensed = $patient_or_order[$i]['days_dispensed_default'];
          //Ensure defaults are Numbers and not NULL because String will turn addition into concat and if NULL is summed with other valied prices then result is still NULL
          $price_dispensed = $patient_or_order[$i]['price_dispensed_default'] ?: 0;
        }

        $patient_or_order[$i]['days_dispensed'] = (float) $days_dispensed;
        $patient_or_order[$i]['price_dispensed'] = (float) $price_dispensed;

        if ($patient_or_order[$i]['days_dispensed']) {
          $count_filled++;
        }

        /*
         * Create some variables with appropriate values
         */
        if ($patient_or_order[$i]['refills_dispensed_actual']) {
          $refills_dispensed = $patient_or_order[$i]['refills_dispensed_actual'];
        } elseif ($patient_or_order[$i]['refills_dispensed_default']) {
          $refills_dispensed = $patient_or_order[$i]['refills_dispensed_default'];
        } else {
          $refills_dispensed = $patient_or_order[$i]['refills_total'];
        }

        $patient_or_order[$i]['refills_dispensed'] = round($refills_dispensed, 2);

        if ($patient_or_order[$i]['qty_dispensed_actual']) {
          $qty_dispensed = $patient_or_order[$i]['qty_dispensed_actual'];
        } else {
          $qty_dispensed = $patient_or_order[$i]['qty_dispensed_default'];
        }

        $patient_or_order[$i]['qty_dispensed'] = (float) $qty_dispensed;
    } //END LARGE FOR LOOP

    if ($items_to_remove) { //WARNING EMPTY OR NULL ARRAY WOULD REMOVE ALL ITEMS
      export_cp_remove_items($patient_or_order[0]['invoice_number'], $items_to_remove);
    }

    if ($items_to_add) {
      export_cp_add_items($patient_or_order[0]['invoice_number'], $items_to_add);
    }

    foreach ($patient_or_order as $i => $item) {
      $patient_or_order[$i]['count_filled']    = $count_filled;
      $patient_or_order[$i]['items_to_remove'] = $items_to_remove;
      $patient_or_order[$i]['items_to_add']    = $items_to_add;
    }

    //A patient profile may have an rx turn on autofill, causing a day change but we still don't have an order to update
    //Don't generate invoice if we are adding/removing drugs on next go-around, since invoice would need to be updated again
    if (@$patient_or_order[0]['invoice_number'] AND $update_payment) {
      $patient_or_order = helper_update_payment($patient_or_order, "helper_full_fields: {$patient_or_order[0]['invoice_number']} items_to_add:".count($items_to_add)." items_to_remove:".count($items_to_remove), $mysql);
    }

    return $patient_or_order;
}
