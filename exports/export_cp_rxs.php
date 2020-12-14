<?php

global $mssql;

/**
 * We want all Rxs within a group to share the same rx_autofill value,
 * so when one changes we must change them all
 * @param  array $item  The rx_numbers to update
 * @param  array $mssql The RX message
 * @return array the original item
 */
function export_cp_set_rx_message($item, $message)
{
    global $mssql;
    $mssql = $mssql ?: new Mssql_Cp();

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
    if (ENVIRONMENT == 'PRODUCTION') {
        $mssql->run($sql1);
    } else {
        echo "Skipping Guardian Writes in Development\n";
    }

    return $item;
}

/**
 * We want all Rxs within a group to share the same rx_autofill value,
 * so when one changes we must change them all
 * @param  array    $item  The rx_numbers to update
 * @param  Mssql_Cp $mssql Carepoint Database Connection
 * @return void
 */
function export_cp_rx_autofill($item, $mssql)
{
    if (ENVIRONMENT == 'PRODUCTION') {
        $rx_numbers  = str_replace(',', "','", substr($item['rx_numbers'], 1, -1)); //use drugs_gsns instead of rx_gsn just in case there are multiple gsns for this drug
        $sql = "UPDATE cprx SET autofill_yn = $item[rx_autofill], chg_date = GETDATE() WHERE script_no IN ('$rx_numbers')";
        $mssql->run($sql);
    } else {
        echo "Skipping Guardian Writes in Development\n";
    }
}
