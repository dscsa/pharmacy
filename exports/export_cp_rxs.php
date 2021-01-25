<?php

global $mssql;

use Sirum\Logging\SirumLog;

function export_cp_set_rx_message($item, $message) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  if ( ! @$item['rx_numbers']) {
    SirumLog::alert("export_cp_set_rx_message: wrong arg passed", [$item, $message]);
  }

  $rx_numbers = str_replace(",", "','", substr($item['rx_numbers'], 1, -1));

  $sql1 = "
    UPDATE
      cprx
    SET
      priority_cn = $message[CP_CODE]
    WHERE
      script_no IN ('$rx_numbers')
  ";

  log_notice('export_cp_set_rx_message', [$sql1]);

  $mssql->run($sql1);

  return $item;
}

// We want all Rxs within a group to share the same rx_autofill value, so when
// one changes we must change them all

// SQL to DETECT inconsistencies:
// SELECT patient_id_cp, rx_gsn, MAX(drug_name), MAX(CONCAT(rx_number, rx_autofill)),
// GROUP_CONCAT(rx_autofill), GROUP_CONCAT(rx_number) FROM gp_rxs_single GROUP BY
// patient_id_cp, rx_gsn HAVING AVG(rx_autofill) > 0 AND AVG(rx_autofill) < 1
function export_cp_rx_autofill($item, $mssql) {

  // Don't try to run this if you don't have data.
  if (!@$item['rx_autofill']) {
      return;
  }

  //use drugs_gsns instead of rx_gsn just in case there are multiple gsns for this drug
  $rx_numbers  = str_replace(',', "','", substr($item['rx_numbers'], 1, -1));

  $sql = "UPDATE cprx
            SET autofill_yn = {$item['rx_autofill']},
                chg_date = GETDATE()
            WHERE script_no IN ('{$rx_numbers}')";

  $mssql->run($sql);
}
