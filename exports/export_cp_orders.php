<?php

global $mssql;

require_once 'exports/export_cp_order_items.php';

use Sirum\Logging\SirumLog;

function export_cp_set_pend_name($item) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $pend_group_name = pend_group_name($item);
  $expected_by     = substr($pend_group_name, 0, 10);

  $sql = "
    UPDATE csom SET expected_by = '$expected_by' WHERE invoice_nbr = $item[invoice_number]
  ";

  SirumLog::notice(
    "export_cp_set_pend_name: pend group name $pend_group_name $item[invoice_number]",
    [
      'expected_by'     => $expected_by,
      'pend_group_name' => $pend_group_name,
      'invoice_number'  => $item['invoice_number'],
      'sql'             => $sql
    ]
  );

  $res = $mssql->run($sql);
}

function export_cp_remove_order($invoice_number) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  SirumLog::notice(
    "export_cp_remove_order: Order deleting $invoice_number",
    [
      'invoice_number'  => $invoice_number
    ]
  );

  $new_count_items = export_cp_remove_items($invoice_number);

  if ( ! $new_count_items) {
    $date = date('Y-m-d H:i:s');
    $sql = "
      UPDATE csom SET status_cn = 3, comments = CONCAT(comments, ' Deleted by Pharmacy App on $date') WHERE invoice_nbr = $invoice_number -- chg_user_id = @user_id, chg_date = @today
    ";

    $res = $mssql->run($sql);

    SirumLog::notice(
      "export_cp_remove_order: Order $invoice_number was deleted",
      [
        'invoice_number'  => $invoice_number,
        'new_count_items' => $new_count_items,
        'sql'             => $sql,
        'res'             => $res
      ]
    );

  } else {

    SirumLog::alert(
      "export_cp_remove_order: Order $invoice_number could only be partially deleted",
      [
        'invoice_number'  => $invoice_number,
        'new_count_items' => $new_count_items
      ]
    );

  }
}
