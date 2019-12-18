<?php
//Example New Surescript Comes in that we want to remove from Queue
function export_cp_remove_item($item, $message) {
  //Notify Patient?

  //TYPES:
  //SureScript Authorization Denied: No Refills 24227
  if ( ! $item['refills_left'])
    log_info("export_cp_remove_item Refill Request Denied: $message", get_defined_vars());

  //New SureScript of Refill Only Item 24225
  else if ( ! $item['refill_date_first'] AND is_refill_only($item))
    log_info("export_cp_remove_item Refill Only Item: $message", get_defined_vars());

  //New SureScript but not due yet 24222
  else if ((strtotime($item['refill_date_next']) - strtotime($item['item_date_added'])) > 15*24*60*60)
    log_info("export_cp_remove_item Not Due Yet: $message", get_defined_vars());

  else
    log_error("export_cp_remove_item UNKNOWN REASON: $message", get_defined_vars());//.print_r($item, true);
}

//Example update_order::sync_to_order() wants to add another item to existing order because its due in 1 week
function export_cp_add_item($item, $message) {
  log_error("export_cp_add_more_items: $message", get_defined_vars());//.print_r($item, true);
}

function export_cp_switch_item($item) { //Move CP from current rx_number to the "best_rx_number"
  log_error("export_cp_switch_item", get_defined_vars());//.print_r($item, true);
}
