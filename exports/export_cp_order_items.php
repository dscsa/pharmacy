<?php

global $mssql;

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

//Example New Surescript Comes in that we want to remove from Queue
//WARNING EMPTY OR NULL ARRAY REMOVES ALL ITEMS
function export_cp_remove_items($invoice_number, $items = [])
{
    global $mssql;
    $mssql = $mssql ?: new Mssql_Cp();
    $cp_automation_user = CAREPOINT_AUTOMATION_USER;

    $order_id   = $invoice_number - 2; //TODO SUPER HACKY
    $sql = "DELETE csomline
                FROM csomline
                    JOIN cprx ON cprx.rx_id = csomline.rx_id
                WHERE csomline.order_id = '{$order_id}'
                    -- if the rxdisp_id is set on the line, you have to
                    -- call CpOmVoidDispense first.
                    AND rxdisp_id = 0";

    $sql_to_update_deleted = "
        UPDATE CsOmLine_Deleted
        SET chg_user_id = {$cp_automation_user}
        FROM CsOmLine_Deleted
        JOIN cprx ON cprx.rx_id = CsOmLine_Deleted.rx_id
        WHERE CsOmLine_Deleted.order_id = '{$order_id}'
        AND rxdisp_id = 0";

    $rx_numbers = [];
    $order_cmts = [];

    foreach ($items as $item) {
    //item_message_keys is not set until dispensed
        $order_cmts[] = "$item[drug_generic] - $item[rx_message_keys]";
        $rx_numbers[] = $item['rx_number'];
    }

    if ($rx_numbers) {
        $sql .= "
      AND script_no IN ('".implode("', '", $rx_numbers)."')
    ";
        $sql_to_update_deleted .= "
            AND script_no IN ('".implode("', '", $rx_numbers)."')
        ";
        $order_cmts = implode(', ', $order_cmts);
    } else {
        $order_cmts = "all drugs";
    }

    $res = $mssql->run($sql);
    $delete_response = $mssql->run($sql_to_update_deleted);
    $date = date('y-m-d H:i');
    //Removing CK said too overwhelming
    //export_cp_append_order_note($mssql, $invoice_number, "$date auto removed: $order_cmts");

    GPLog::debug(
        "export_cp_remove_items: $invoice_number",
        [
            'invoice_number'               => $invoice_number,
            'rx_numbers'                   => $rx_numbers,
            'items'                        => $items,
            'sql'                          => $sql,
            'res'                          => $res,
            'sql_to_update_deleted_line'   => $sql_to_update_deleted,
            'response_from_update_deleted' => $delete_response
        ]
    );

    $new_count_items = export_cp_recount_items($invoice_number, $mssql);

    return $new_count_items;
}

function export_cp_recount_items($invoice_number, $mssql)
{
    $order_id = $invoice_number - 2; //TODO SUPER HACKY
    $cp_automation_user = CAREPOINT_AUTOMATION_USER;

    $sql1 = "SELECT COUNT(*) as count_items
                FROM csomline
                WHERE order_id = '{$order_id}'";

    $new_count_items = (int) $mssql->run($sql1)[0][0]['count_items'];

    $sql2 = "UPDATE csom
                SET csom.liCount = {$new_count_items},
                    csom.chg_user_id = {$cp_automation_user}
                WHERE order_id = '{$order_id}'";

    $res = $mssql->run($sql2);

    GPLog::debug(
        "export_cp_recount_items: $invoice_number",
        [
          'invoice_number'  => $invoice_number,
          'new_count_items' => $new_count_items,
          'sql1'            => $sql1,
          'sql2'            => $sql2,
          'res'             => $res
        ]
    );

    return $new_count_items;
}

//Example update_order::sync_to_order() wants to add another item to existing order because its due in 1 week
function export_cp_add_items($invoice_number, $items)
{
    $rx_numbers = [];
    $order_cmts = [];


    //rx_number only set AFTER its added.  We need to choose which to add, so use best.
    foreach ($items as $item) {
        if (! @$item['rx_message_key']) {
            GPLog::debug(
                "export_cp_add_items: $invoice_number rx_message_key is not set",
                [
                    'invoice_number' => $invoice_number,
                    'item'           => $item
                ]
            );
        }

        $rx_numbers[] = $item['best_rx_number'];
        $order_cmts[] = "$item[drug_generic] - $item[rx_message_key]";
    }

    if (! $rx_numbers) {
        return;
    }

    $rx_numbers = json_encode($rx_numbers);
    $order_cmts = implode(', ', $order_cmts);

    global $mssql;
    global $mysql;

    $mssql = $mssql ?: new Mssql_Cp();
    $mysql = $mysql ?: new Mysql_Wc();

    if (! $invoice_number) {
        $sql = " SELECT
                    invoice_number
                  FROM
                    gp_orders
                  WHERE
                    order_date_dispensed IS NULL AND
                    patient_id_cp = {$items[0]['patient_id_cp']}";

        $current_order = $mysql->run($sql)[0];

        $invoice_number = @$current_order[0]['invoice_number'];

        $log = [
              "subject"        => "Item needs to be added but no order",
              "msg"            => "Confirm this is always an rx-created2/updated "
                                  . "or deleted order-item (i understand former but "
                                  . "not latter). Find current order if one exists. "
                                  . "Maybe even create a new order if one doesn't exist?",
              "invoice_number" => @$items[0]['invoice_number'],
              "item_invoice"   => @$items[0]['dontuse_item_invoice'],
              "order_invoice"  => @$items[0]['dontuse_order_invoice'],
              'sql'            => $sql,
              'items'          => $items,
              'current_order'  => $current_order
        ];

        if (!$invoice_number) {
            // Lets see if there is one in the gp_orders_cp table
            $sql = "SELECT
                        invoice_number
                      FROM
                        gp_orders_cp
                      WHERE
                        order_date_dispensed IS NULL AND
                        patient_id_cp = {$items[0]['patient_id_cp']}";

            $cp_invoice_number = $mysql->run($sql)[0][0]['invoice_number'];

            $log['has_cp_order'] = (@$cp_invoice_number) ? 'Y' : 'N';

            GPLog::warning("{$log['subject']}, why are the RX importing before the actual order?<--BB", $log);
            return;
        }

        GPLog::notice("{$log['subject']}, so adding to {$invoice_number} instead", $log);
    }

    $sql  = "SirumWeb_AddItemsToOrder '{$invoice_number}', '{$rx_numbers}'";
    $res  = $mssql->run($sql);
    $date = date('y-m-d H:i');

    GPLog::warning(
        "export_cp_add_items $invoice_number",
        [
            'invoice_number' => $invoice_number,
            'sql'            => $sql,
            'items'          => $items
        ]
    );
}
