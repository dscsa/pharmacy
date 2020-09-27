<?php
require_once 'exports/export_cp_rxs.php';
require_once 'helpers/helper_full_fields.php';

function get_full_patient($partial, $mysql, $overwrite_rx_messages = false) {

  if ( ! isset($partial['patient_id_cp'])) {
    log_error('ERROR! get_full_patient: was not given a patient_id_cp', $partial);
    return;
  }

  $month_interval = 6;
  $where = "
    AND (CASE WHEN refills_total THEN gp_rxs_grouped.rx_date_expired ELSE COALESCE(gp_rxs_grouped.rx_date_transferred, gp_rxs_grouped.refill_date_last) END) > CURDATE() - INTERVAL $month_interval MONTH
  ";

  $sql = "
    SELECT
      *,
      gp_rxs_grouped.* -- Need to put this first based on how we are joining, but make sure these grouped fields overwrite their single equivalents
    FROM
      gp_patients
    LEFT JOIN gp_rxs_grouped ON -- Show all Rxs on Invoice regardless if they are in order or not
      gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
    LEFT JOIN gp_rxs_single ON -- Needed to know qty_left for sync-to-date
      gp_rxs_grouped.best_rx_number = gp_rxs_single.rx_number
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic -- this is for the helper_days_dispensed msgs for unordered drugs
    WHERE
      gp_patients.patient_id_cp = $partial[patient_id_cp]
  ";

  //If we are not overwritting messages just get recent scripts, otherwise make sure we get all the rxs so we can overwrite them
  $patient = $overwrite_rx_messages ? $mysql->run($sql)[0] : $mysql->run($sql.$where)[0];

  if ( ! $patient OR ! $patient[0]['patient_id_cp']) {
    //log_error("ERROR! get_full_patient: no active patient with id:$partial[patient_id_cp] #1 of 2. Import Order Error or No Recent Rxs?", get_defined_vars());

    $patient = $mysql->run($sql)[0];

    if ( ! $patient OR ! $patient[0]['patient_id_cp']) {
      log_error("ERROR! get_full_patient: no active patient with id:$partial[patient_id_cp] #2 of 2. Deceased or Inactive Patient with Rxs", get_defined_vars());
      return;
    }
  }

  $patient = add_full_fields($patient, $mysql, $overwrite_rx_messages);
  usort($patient, 'sort_patient_by_drug'); //Put Rxs in order (with Rx_Source) at the top
  $patient = add_sig_differences($patient);

  return $patient;
}

function add_sig_differences($patient) {

  $drug_names = []; //Append qty_per_day if multiple of same strength, do this after sorting

  foreach($patient as $i => $item) {
    if (isset($drug_names[$item['drug']])) {
      $patient[$i]['drug'] .= ' ('.( (float) $item['sig_qty_per_day'] ).' per day)';
      //log_notice("helper_full_patient add_sig_differences: appended sig_qty_per_day to duplicate drug ".$item['drug']." >>> ".$drug_names[$item['drug']], [$order, $item, $drug_names]);
    } else {
      $drug_names[$item['drug']] = $item['sig_qty_per_day'];
    }
  }

  return $patient;
}

function sort_patient_by_drug($a, $b) {
  if ($b['drug_generic'] > 0 AND $a['drug_generic'] == 0) return 1;
  if ($a['drug_generic'] > 0 AND $b['drug_generic'] == 0) return -1;
  return strcmp($a['rx_message_text'].$a['drug'], $b['rx_message_text'].$b['drug']);
}
