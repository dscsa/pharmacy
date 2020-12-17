<?php

global $mssql;

use Sirum\Logging\SirumLog;


function export_cp_remove_order($invoice_number) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

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



//Example New Surescript Comes in that we want to remove from Queue
function export_cp_remove_items($invoice_number, $script_nos = []) {

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

  if ($script_nos) {
    $sql .= "
      AND script_no IN ('".implode("', '", $script_nos)."')
    ";
  }

  $res = $mssql->run($sql);

  $new_count_items = export_cp_recount_items($invoice_number, $mssql);

  SirumLog::debug(
    "export_cp_remove_items: $invoice_number",
    [
      'invoice_number'  => $invoice_number,
      'new_count_items' => $new_count_items,
      'script_nos'      => $script_nos,
      'sql'            => $sql,
      'res'            => $res
    ]
  );

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
function export_cp_add_items($invoice_number, $script_nos) {

  if ($script_nos) $script_nos = json_encode($script_nos);
  else return;

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $sql = "SirumWeb_AddItemsToOrder '$invoice_number', '$script_nos'";

  $res = $mssql->run($sql);

  log_notice("export_cp_add_items", ['sql' => $sql]);
}
