<?php

global $mssql;

function export_cp_set_rx_message($item, $message, $mysql) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $old_rx_message_key  = $item['rx_message_key'];
  $old_rx_message_text = $item['rx_message_text'];

  $item['rx_message_key']  = array_search($message, RX_MESSAGE);
  $item['rx_message_text'] = message_text($message, $item);

  if ( ! $item['rx_message_key']) {
    log_error("set_days_default could not get rx_message_key ", get_defined_vars());
    return $item;
  }

  if ( ! @$item['days_dispensed_default'])
    $item['rx_message_text'] .= ' **'; //If not filling reference to backup pharmacy footnote on Invoices

  $rx_numbers = str_replace(",", "','", substr($item['rx_numbers'], 1, -1));

  $sql1 = "
    UPDATE
      cprx
    SET
      priority_cn = $message[CP_CODE]
    WHERE
      script_no IN ('$rx_numbers')
  ";

  $sql2 = "
    UPDATE
      gp_rxs_single
    SET
      rx_message_key  = '$item[rx_message_key]',
      rx_message_text = '".escape_db_values($item['rx_message_text'])."'
    WHERE
      rx_number IN ('$rx_numbers')
  ";

  //This Group By Clause must be kept consistent with the grouping with the update_rxs_single.php query
  $sql3 = "
    UPDATE
      gp_rxs_grouped
    SET
      rx_message_keys = (
        SELECT
          GROUP_CONCAT(DISTINCT rx_message_key) as rx_message_keys
        FROM gp_rxs_single
        LEFT JOIN gp_drugs ON
          drug_gsns LIKE CONCAT('%,', rx_gsn, ',%')
        WHERE
          rx_number IN ('$rx_numbers')
        GROUP BY
          patient_id_cp,
          COALESCE(drug_generic, drug_name),
          COALESCE(sig_qty_per_day_actual, sig_qty_per_day_default)
      )
    WHERE
      best_rx_number IN ('$rx_numbers')
  ";

  log_notice('export_cp_set_rx_message', [$sql1, $sql2, $sql3]);

  $mssql->run($sql1);
  $mysql->run($sql2);
  $mysql->run($sql3);

  return $item;
}

//We want all Rxs within a group to share the same rx_autofill value, so when one changes we must change them all
//SQL to DETECT inconsistencies:
//SELECT patient_id_cp, rx_gsn, MAX(drug_name), MAX(CONCAT(rx_number, rx_autofill)), GROUP_CONCAT(rx_autofill), GROUP_CONCAT(rx_number) FROM gp_rxs_single GROUP BY patient_id_cp, rx_gsn HAVING AVG(rx_autofill) > 0 AND AVG(rx_autofill) < 1
function export_cp_rx_autofill($item, $mssql) {
  $rx_numbers  = str_replace(',', "','", substr($item['rx_numbers'], 1, -1)); //use drugs_gsns instead of rx_gsn just in case there are multiple gsns for this drug
  $sql = "UPDATE cprx SET autofill_yn = $item[rx_autofill], chg_date = GETDATE() WHERE script_no IN ('$rx_numbers')";
  $mssql->run($sql);
}
