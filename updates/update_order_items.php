<?php

require_once 'changes/changes_to_order_items.php';
require_once 'helpers/helper_days_dispensed.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
//require_once 'exports/export_gd_transfer_fax.php';

function update_order_items() {

  $changes = changes_to_order_items('gp_order_items_cp');

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "
  update_order_items: $count_deleted deleted, $count_created created, $count_updated updated. ";

  if ($count_deleted+$count_created+$count_updated)
    log_info($message.print_r($changes, true));

  //email("CRON: $message", $message, $changes);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  function get_full_item($item, $mysql) {

    /* ORDER MAY HAVE NOT BEEN ADDED YET
    JOIN gp_orders ON
      gp_orders.invoice_number = gp_order_items.invoice_number
    */

    $sql = "
      SELECT *
      FROM
        gp_order_items
      JOIN gp_rxs_grouped ON
        rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
      JOIN gp_rxs_single ON
        gp_order_items.rx_number = gp_rxs_single.rx_number
      JOIN gp_patients ON
        gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
      LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
        gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
      WHERE
        gp_order_items.invoice_number = $item[invoice_number] AND
        gp_order_items.rx_number = $item[rx_number]
    ";


    $full_item  = [];
    $query = $mysql->run($sql);

    if (isset($query[0][0])) {
      $full_item = $query[0][0];

      if ( ! $full_item['stock_level'])
        email('ERROR get_full_item: missing stock level', $item, $full_item, $query, $sql);

    } else {

      $debug = "
        SELECT *, , gp_order_items.rx_number as rx_number --otherwise gp_rx_single.rx_number overwrites
        FROM
          gp_order_items
        LEFT JOIN gp_rxs_grouped ON
          rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
        LEFT JOIN gp_rxs_single ON
          gp_order_items.rx_number = gp_rxs_single.rx_number
        LEFT JOIN gp_patients ON
          gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
        LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
          gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
        WHERE
          gp_order_items.invoice_number = $item[invoice_number] OR
          gp_order_items.rx_number = $item[rx_number]
      ";

      $anything = $mysql->run($debug);

      email("ERROR get_full_item: missing item", $item, $full_item, $sql, $query, $debug, $anything);
    }

    log_info("
    Item: $sql
    ".print_r($full_item, true));

    return $full_item;
  }

  //If just added to CP Order we need to
  //  - determine "days_dispensed_default" and "qty_dispensed_default"
  //  - pend in v2 and save applicable fields
  //  - if first line item in order, find out any other rxs need to be added
  //  - update invoice
  //  - update wc order total
  foreach($changes['created'] as $created) {

    $item = get_full_item($created, $mysql);

    email('update_order_items created', $created, $item);

    list($days, $message) = get_days_dispensed($item);

    set_days_dispensed($item, $days, $message, $mysql);

    if ( ! $days) {
      export_cp_remove_item($item);
      //export_gd_transfer_fax($item);
      continue;
    }

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    $item = get_full_item($deleted, $mysql);

    set_days_dispensed($item, 0, $status, $mysql);

    export_v2_remove_pended($item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    $item = get_full_item($updated, $mysql);

    email('update_order_items updated', $item);

    if ($item['days_dispensed_default']) {

      log_info("Updated Item No Action: ");

    } else {

      list($days, $message) = get_days_dispensed($item);

      set_days_dispensed($item, $days, $message, $mysql);
    }

    log_info("Order Items: ".print_r(changed_fields($updated), true).print_r($updated, true));

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }
}
