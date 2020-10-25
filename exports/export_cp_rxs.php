<?php

global $mssql;

/**
 * Copy the perscription message over to the CarePoint database
 *
 * @param array    $item    The item and it's details
 * @param string   $message The perscription messages
 * @param Mssql_Cp $mysql   The CarePoint databse object
 *
 * @return void
 */
function export_cp_set_rx_message($item, $message, $mysql)
{
    global $mssql;
    $mssql = $mssql ?: new Mssql_Cp();

    $old_rx_message_key  = $item['rx_message_key'];
    $old_rx_message_text = $item['rx_message_text'];

    $item['rx_message_key']  = array_search($message, RX_MESSAGE);
    $item['rx_message_text'] = message_text($message, $item);

    // There isn't a perscription message so lets bugout
    if (! $item['rx_message_key']) {
        log_error("set_days_default could not get rx_message_key ", get_defined_vars());
        return $item;
    }

    //If not filling reference to backup pharmacy footnote on Invoices
    if (!isset($item['days_dispensed_default']) || !$item['days_dispensed_default']) {
        $item['rx_message_text'] .= ' **';
    }

    $rx_numbers = str_replace(",", "','", substr($item['rx_numbers'], 1, -1));

    $sql1 = "UPDATE
                cprx
              SET
                priority_cn = $message[CP_CODE]
              WHERE
                script_no IN ('{$rx_numbers}')";

    $sql2 = "UPDATE
                gp_rxs_single
              SET
                rx_message_key  = '{$item['rx_message_key']}',
                rx_message_text = '" . escape_db_values($item['rx_message_text']) . "'
              WHERE
                rx_number IN ('{$rx_numbers}')";

    if (ENVIRONMENT == 'PRODUCTION') {
        $mssql->run($sql1);
        $mysql->run($sql2);
    }
    return $item;
}
