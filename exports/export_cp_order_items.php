<?php

global $mssql;

//Example New Surescript Comes in that we want to remove from Queue
function export_cp_remove_items($invoice_number, $script_nos) {

  if ($script_nos) $script_nos = json_encode($script_nos);
  else return;

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $sql = "SirumWeb_RemoveScriptNosFromOrder '$invoice_number', '$script_nos'";

  //$res = $mssql->run($sql);

  log_notice("export_cp_remove_items", get_defined_vars());
}

//Example update_order::sync_to_order() wants to add another item to existing order because its due in 1 week
function export_cp_add_items($invoice_number, $script_nos) {

  if ($script_nos) $script_nos = json_encode($script_nos);
  else return;

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();

  $sql = "SirumWeb_AddScriptNosToOrder '$invoice_number', '$script_nos'";

  $res = $mssql->run($sql);

  log_notice("export_cp_add_items", get_defined_vars());
}
