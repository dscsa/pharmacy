<?php

require_once 'changes/changes_to_order_items.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
require_once 'exports/export_gd_orders.php';
require_once 'exports/export_wc_orders.php';

function update_order_items() {

  $changes = changes_to_order_items('gp_order_items_cp');

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "update_order_items $count_deleted deleted, $count_created created, $count_updated updated. ";

  echo $message;

  mail('adam@sirum.org', "CRON: $message", $message.print_r($changes, true));

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  function join_all_tables($order_item, $mysql) {
    return $mysql->run("
      SELECT *
      FROM
        gp_order_items
      JOIN gp_rxs_grouped ON
        rx_numbers LIKE CONCAT('%,', rx_number, ',%')
      JOIN gp_stock_live ON
        gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
      JOIN gp_patients ON
        gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
      WHERE
        rx_number = $order_item[rx_number]
    ")[0][0];
  }

  //If just added to CP Order we need to
  //  - determine "days_dispensed_default" and "qty_dispensed_default"
  //  - pend in v2 and save applicable fields
  //  - if first line item in order, find out any other rxs need to be added
  //  - update invoice
  //  - update wc order total
  foreach($changes['created'] as $created) {

    $order_item = join_all_tables($created, $mysql);

    $days = get_days_dispensed($order_item);

    if ( ! $days)
      return export_cp_remove_item($order_item);

    set_days_dispensed($days);

    export_v2_add_pended($order_item);

    export_cp_add_more_items($order_item); //this will cause another update and we will end back in this loop

    export_gd_update_invoice($order_item);

    export_wc_update_order($order_item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    $order_item = join_all_tables($deleted, $mysql);

    set_days_dispensed(0);

    export_v2_remove_pended($order_item);

    export_cp_remove_more_items($order_item); //this will cause another update and we will end back in this loop

    export_gd_update_invoice($order_item);

    export_wc_update_order($order_item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    $order_item = join_all_tables($updated, $mysql);
    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    export_gd_update_invoice($order_item);

    export_wc_update_order($order_item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }
}
