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

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_order_items: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();

  function get_full_item($item, $mysql) {

    if ( ! $item['invoice_number'] OR ! $item['rx_number']) {
      log_error('ERROR get_full_item: missing invoice_number or rx_number', get_defined_vars());
      return [];
    }

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

      if ( ! $full_item['drug_generic'])
        log_error('Missing GSN!', get_defined_vars());

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

      log_error("Missing Order Item!", get_defined_vars());
    }

    //log_info("Get Full Item", get_defined_vars());

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

    if ($item['days_dispensed_actual']) {

      log_error("Created Item Readded", get_defined_vars());

      set_days_actual($item, $mysql);

    } else {

      list($days, $message) = get_days_default($item);

      set_days_default($item, $days, $message, $mysql);

      if ( ! $days) {
        export_cp_remove_item($item);
        //export_gd_transfer_fax($item);
        continue;
      }
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

    set_days_default($item, 0, $status, $mysql);

    export_v2_remove_pended($item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    $item = get_full_item($updated, $mysql);

    $changed_fields = changed_fields($updated);

    if ($item['days_dispensed_actual']) {

      set_days_actual($item, $mysql);

    } else if ( ! $item['days_dispensed_default']) {

      log_error("Updated Item has no days_dispensed_default.  Was GSN added?", get_defined_vars());

      list($days, $message) = get_days_default($item);

      set_days_default($item, $days, $message, $mysql);

      if ( ! $days) {
        export_cp_remove_item($item);
        //export_gd_transfer_fax($item);
        continue;
      }

    } else {
      log_info("Updated Item No Action", get_defined_vars());
    }

    //log_info("update_order_items", get_defined_vars());

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }
}
