<?php

global $mssql;

/**
 * Remove the perscription numbers from carepoint
 * @param  int $invoice_number The carepoint numbers
 * @param  array $script_nos   an array of script numbers
 * @return void
 */
function export_cp_remove_items($invoice_number, $script_nos)
{
    if (!$script_nos) {
        return;
    }

    global $mssql;
    $mssql = $mssql ?: new Mssql_Cp();

    $order_id   = $invoice_number - 2; //TODO SUPER HACKY
    $script_nos = "('".implode("', '", $script_nos)."')";


    $sql1 = "DELETE csomline
              FROM csomline
              JOIN cprx ON cprx.rx_id = csomline.rx_id
              WHERE csomline.order_id = '{$order_id}'
                AND script_no IN {$script_nos}
                AND rxdisp_id = 0 ";

    //-- if the rxdisp_id is set on the line, you have to call CpOmVoidDispense first.

    $sql2 = "UPDATE csom
              SET csom.liCount = (
                SELECT COUNT(*)
                  FROM csomline
                  WHERE order_id = '{$order_id}'
                )
              WHERE order_id = '{$order_id}'";

    if (ENVIRONMENT == 'PRODUCTION') {
        $res = $mssql->run($sql1);
        $res = $mssql->run($sql2);
    } else {
        echo "Skipping Guardian Writes in Development\n";
    }
}

/**
 * Add ordered item to CarePoint order
 * @param int   $invoice_number Carepoint nvoice number
 * @param array $script_nos     List of script numbers
 *
 * @return void
 */
function export_cp_add_items($invoice_number, $script_nos)
{
    global $mssql;

    if ($script_nos) {
        $script_nos = json_encode($script_nos);
    } else {
        return;
    }

    $mssql = $mssql ?: new Mssql_Cp();
    $sql   = "SirumWeb_AddItemsToOrder '{$invoice_number}', '{$script_nos}'";

    if (ENVIRONMENT == 'PRODUCTION') {
        $res = $mssql->run($sql);
    } else {
        echo "Skipping Guardian Writes in Development\n";
    }
}
