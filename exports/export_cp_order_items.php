<?php
//Example New Surescript Comes in that we want to remove from Queue
function export_cp_remove_item($item) {
  //Notify Patient?
  log_info("
  export_cp_remove_item ");//.print_r($item, true);
}

//Example update_order::sync_to_order() wants to add another item to existing order because its due in 1 week
function export_cp_add_item($item) {
  log_info("
  export_cp_add_more_items ");//.print_r($item, true);
}

function export_cp_switch_item($item) { //Move CP from current rx_number to the "best_rx_number"
  log_info("
  export_cp_switch_item ");//.print_r($item, true);
}
