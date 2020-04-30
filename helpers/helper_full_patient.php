<?php
//order created -> add any additional rxs to order -> import order items -> sync all drugs in order
require_once 'exports/export_cp_rxs.php';
require_once 'helper/helper_full_fields.php';

function get_full_patient($partial, $mysql, $overwrite_rx_messages = false) {

  if ( ! isset($partial['patient_id_cp'])) {
    log_error('ERROR! get_full_patient: was not given a patient_id_cp', $partial);
    return;
  }

  $month_interval = 6;
  $where = "
    AND (CASE WHEN refills_total OR item_date_added THEN gp_rxs_grouped.rx_date_expired ELSE COALESCE(gp_rxs_grouped.rx_date_transferred, gp_rxs_grouped.refill_date_last) END) > CURDATE() - INTERVAL $month_interval MONTH
  ";

  //gp_orders.invoice_number and other fields at end because otherwise potentially null gp_order_items.invoice_number will override gp_orders.invoice_number
  //Don't fully understand LEFT vs RIGHT Joins but experimented around on a missing full_order that had 0 results (#33374) until I got the max number of rows
  $sql = "
    SELECT
      *,
      gp_rxs_grouped.* -- Need to put this first based on how we are joining, but make sure these grouped fields overwrite their single equivalents
    FROM
      gp_patients
    LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
      gp_rxs_grouped.patient_id_cp = gp_orders.patient_id_cp
    LEFT JOIN gp_rxs_single ON -- Needed to know qty_left for sync-to-date
      gp_rxs_grouped.best_rx_number = gp_rxs_single.rx_number
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic -- this is for the helper_days_dispensed msgs for unordered drugs
    WHERE
      gp_patients.patient_id_cp = $partial[patient_id_cp]
  ";


  $order = $mysql->run($sql.$where)[0];

  if ( ! $order OR ! $order[0]['patient_id_cp']) {
    log_error("ERROR! get_full_patient: no active patient with id:$partial[patient_id_cp]. No Rxs? Patient just registered (invoice number is set and used in query but no items so order not imported from CP)?", get_defined_vars());
    return;
  }

  $order = add_full_fields($order, $mysql, $overwrite_rx_messages);
  usort($order, 'sort_patient_by_drug'); //Put Rxs in order (with Rx_Source) at the top
  $order = add_wc_status_to_order($order);

  return $order;
}

function add_wc_status_to_order($order) {

  $order_stage_wc = get_order_stage_wc($order);
  $drug_names     = []; //Append qty_per_day if multiple of same strength, do this after sorting

  foreach($order as $i => $item) {
    $order[$i]['order_stage_wc'] = $order_stage_wc;

    if (isset($drug_names[$item['drug']])) {
      $order[$i]['drug'] .= ' ('.( (float) $item['sig_qty_per_day'] ).' per day)';
      //log_notice("helper_full_order add_wc_status_to_order: appended sig_qty_per_day to duplicate drug ".$item['drug']." >>> ".$drug_names[$item['drug']], [$order, $item, $drug_names]);
    } else {
      $drug_names[$item['drug']] = $item['sig_qty_per_day'];
    }
  }

  return $order;
}

function sort_patient_by_drug($a, $b) {
  if ($b['drug_generic'] > 0 AND $a['drug_generic'] == 0) return 1;
  if ($a['drug_generic'] > 0 AND $b['drug_generic'] == 0) return -1;
  return strcmp($a['rx_message_text'].$a['drug'], $b['rx_message_text'].$b['drug']);
}
