<?php

global $mssql;

use Sirum\Logging\SirumLog;


function export_cp_remove_order($invoice_number) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $new_count_items = export_cp_remove_items($invoice_number);

  if ( ! $new_count_items) {
    $sql = "
      UPDATE csom SET status_cn = 3 WHERE invoice_nbr = $invoice_number -- chg_user_id = @user_id, chg_date = @today
    ";

    $res = $mssql->run($sql);

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


  //$sql = "SirumWeb_RemoveScriptNosFromOrder '$invoice_number', '$script_nos'";

  $sql1 = "
    DELETE csomline
    FROM csomline
    JOIN cprx ON cprx.rx_id = csomline.rx_id
    WHERE csomline.order_id = '$order_id'
    AND rxdisp_id = 0 -- if the rxdisp_id is set on the line, you have to call CpOmVoidDispense first.
  ";

  if ($script_nos) {
    $sql1 .= "
      AND script_no IN ('".implode("', '", $script_nos)."')
    ";
  }

  $res1 = $mssql->run($sql1);

  $sql2 = "
    SELECT COUNT(*) as count_items FROM csomline WHERE order_id = '$order_id'
  ";

  $new_count_items = $mssql->run($sql2)[0][0]['count_items'];

  $sql3 = "
    UPDATE csom
    SET csom.liCount = $new_count_items
    WHERE order_id = '$order_id'
  ";

  $res3 = $mssql->run($sql3);

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

  //log_error("export_cp_add_items", get_defined_vars());
}
