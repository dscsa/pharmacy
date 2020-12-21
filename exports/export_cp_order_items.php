<?php

global $mssql;

use Sirum\Logging\SirumLog;

//Example New Surescript Comes in that we want to remove from Queue
//WARNING EMPTY OR NULL ARRAY REMOVES ALL ITEMS
function export_cp_remove_items($invoice_number, $rx_numbers = []) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $order_id   = $invoice_number - 2; //TODO SUPER HACKY

  $sql = "
    DELETE csomline
    FROM csomline
    JOIN cprx ON cprx.rx_id = csomline.rx_id
    WHERE csomline.order_id = '$order_id'
    AND rxdisp_id = 0 -- if the rxdisp_id is set on the line, you have to call CpOmVoidDispense first.
  ";

  if ($rx_numbers) {
    $sql .= "
      AND script_no IN ('".implode("', '", $rx_numbers)."')
    ";
  }

  $res = $mssql->run($sql);

  SirumLog::debug(
    "export_cp_remove_items: $invoice_number",
    [
      'invoice_number'  => $invoice_number,
      'rx_numbers'      => $rx_numbers,
      'sql'             => $sql,
      'res'             => $res
    ]
  );

  $new_count_items = export_cp_recount_items($invoice_number, $mssql);

  return $new_count_items;
}

function export_cp_recount_items($invoice_number, $mssql) {

  $order_id = $invoice_number - 2; //TODO SUPER HACKY

  $sql1 = "
    SELECT COUNT(*) as count_items FROM csomline WHERE order_id = '$order_id'
  ";

  $new_count_items = (int) $mssql->run($sql1)[0][0]['count_items'];

  $sql2 = "
    UPDATE csom
    SET csom.liCount = $new_count_items
    WHERE order_id = '$order_id'
  ";

  $res = $mssql->run($sql2);

  SirumLog::debug(
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
function export_cp_add_items($invoice_number, $items) {

  $rx_numbers = [];

  foreach ($items as $item) {
    $rx_numbers[] = $item['rx_number'];
  }

  if ( ! $rx_numbers) return;

  $rx_numbers = json_encode($rx_numbers);

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $sql = "SirumWeb_AddItemsToOrder '$invoice_number', '$rx_numbers'";

  $res = $mssql->run($sql);

  log_notice("export_cp_add_items", ['invoice_number' => $invoice_number, 'sql' => $sql, 'items' => $items]);
}
