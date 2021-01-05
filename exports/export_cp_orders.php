<?php

global $mssql;

require_once 'exports/export_cp_order_items.php';

use Sirum\Logging\SirumLog;

function export_cp_set_expected_by($item) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $pend_group_name = pend_group_name($item);
  $expected_by     = substr($pend_group_name, 0, 10);

  $sql = "
    UPDATE csom SET expected_by = '$expected_by' WHERE invoice_nbr = $item[invoice_number]
  ";

  SirumLog::notice(
    "export_cp_set_expected_by: pend group name $pend_group_name $item[invoice_number]",
    [
      'expected_by'     => $expected_by,
      'pend_group_name' => $pend_group_name,
      'invoice_number'  => $item['invoice_number'],
      'sql'             => $sql
    ]
  );

  $res = $mssql->run($sql);
}

function export_cp_remove_order($invoice_number, $reason) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  SirumLog::notice(
    "export_cp_remove_order: Order deleting $invoice_number",
    [
      'invoice_number'  => $invoice_number,
      'reason'          => $reason
    ]
  );

  $new_count_items = export_cp_remove_items($invoice_number); //since no 2nd argument this removes all undispensed items

  if ( ! $new_count_items) { //if no items were dispensed yet, archive order

    $sql = "
      UPDATE csom SET status_cn = 3 WHERE invoice_nbr = $invoice_number -- chg_user_id = @user_id, chg_date = @today
    ";

    $res = $mssql->run($sql);

    $date = date('y-m-d H:i');
    export_cp_append_order_note($mssql, $invoice_number, "Auto Deleted $date. $reason");

    SirumLog::notice(
      "export_cp_remove_order: Order $invoice_number was deleted",
      [
        'invoice_number'  => $invoice_number,
        'reason'          => $reason,
        'new_count_items' => $new_count_items,
        'sql'             => $sql,
        'res'             => $res
      ]
    );

  } else {

    SirumLog::alert(
      "export_cp_remove_order: Order $invoice_number had dispensed items and could only be partially deleted",
      [
        'invoice_number'  => $invoice_number,
        'reason'          => $reason,
        'new_count_items' => $new_count_items
      ]
    );

  }
}

function export_cp_append_order_note($mssql, $invoice_number, $note) {

    $sql = "
      UPDATE csom SET comments = RIGHT(CONCAT(comments, CHAR(10), '$note'), 255) WHERE invoice_nbr = $invoice_number -- chg_user_id = @user_id, chg_date = @today
    ";

    $res = $mssql->run($sql);

    SirumLog::notice(
      "export_cp_append_order_note: Order $invoice_number had note appended: $note",
      [
        'invoice_number'  => $invoice_number,
        'note'            => $note,
        'sql'             => $sql,
        'res'             => $res
      ]
    );
}
