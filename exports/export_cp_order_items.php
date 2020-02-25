<?php

global $mssql;

//Example New Surescript Comes in that we want to remove from Queue
function export_cp_remove_items($invoice_number, $script_nos) {

  if ( ! $script_nos) return;

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $order_id   = $invoice_number - 2; //TODO SUPER HACKY
  $script_nos = "('".implode("', '", $script_nos)."')";

  //$sql = "SirumWeb_RemoveScriptNosFromOrder '$invoice_number', '$script_nos'";

  $sql1 = "
    DELETE csomline
    FROM csomline
    JOIN cprx ON cprx.rx_id = csomline.rx_id
    WHERE csomline.order_id = '$order_id'
    AND script_no IN $script_nos
    AND rxdisp_id = 0 -- if the rxdisp_id is set on the line, you have to call CpOmVoidDispense first.
  ";

  $sql2 = "
    UPDATE csom
    SET csom.liCount = (SELECT COUNT(*) FROM csomline WHERE order_id = '$order_id')
    WHERE order_id = '$order_id'
  ";

  $res = $mssql->run($sql1);
  $res = $mssql->run($sql2);
}

//Example update_order::sync_to_order() wants to add another item to existing order because its due in 1 week
function export_cp_add_items($invoice_number, $script_nos) {

  if ($script_nos) $script_nos = json_encode($script_nos);
  else return;

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $sql = "SirumWeb_AddScriptNosToOrder '$invoice_number', '$script_nos'";

  $res = $mssql->run($sql);

  log_error("export_cp_add_items", get_defined_vars());
}
