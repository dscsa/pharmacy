<?php

require_once 'helpers/helper_days_and_message.php';
require_once 'helpers/helper_full_item.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
require_once 'exports/export_gd_transfer_fax.php';

use Sirum\Logging\SirumLog;

function update_order_items($changes) {

  $changes = changes_to_order_items('gp_order_items_cp');

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  SirumLog::debug(
    'Order items changes found',
    [
      'deleted' => $changes['deleted'],
      'created' => $changes['created'],
      'updated' => $changes['updated'],
      'deleted_count' => $count_deleted,
      'created_count' => $count_created,
      'updated_count' => $count_updated
    ]
  );

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_order_items: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  //If just added to CP Order we need to
  //  - determine "days_dispensed_default" and "qty_dispensed_default"
  //  - pend in v2 and save applicable fields
  //  - if first line item in order, find out any other rxs need to be added
  //  - update invoice
  //  - update wc order total
  foreach($changes['created'] as $created) {

    SirumLog::$subroutine_id = "order-items-created-".sha1(serialize($created));

    $item = get_full_item($created, $mysql, true);

    SirumLog::debug(
      "update_order_items: Order Item created",
      [
          'item'    => $item,
          'created' => $created,
          'source'  => 'CarePoint',
          'type'    => 'order-items',
          'event'   => 'created'
      ]
    );

    if ( ! $item) {
      log_error("Created Item Missing", $created);
      continue;
    }

    //We don't pend inventory in v2 here (v2_pend_item), but at the order level, in case we want to sync any drugs to the order, or vary the days to sync drugs to a date

    if ($item['days_dispensed_actual']) {

      log_error("order_item created but days_dispensed_actual already set.  Most likely an new rx but not part of a new order (days actual is from a previously shipped order) or an item added to order and dispensed all within the time between cron jobs", [$item, $created]);

      freeze_invoice_data($item, $mysql);
      continue;
    }

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  foreach($changes['deleted'] as $deleted) {

    SirumLog::$subroutine_id = "order-items-deleted-".sha1(serialize($deleted));

    SirumLog::debug(
      "update_order_items: Order Item deleted",
      [
          'deleted' => $deleted,
          'source'  => 'CarePoint',
          'type'    => 'order-items',
          'event'   => 'deleted'
      ]
    );

    $item = get_full_item($deleted, $mysql, true);

    //Don't Unpend here.  This is handled by count_item changes in update_orders_cp
    //Count Items will go down, triggering a CP Order Change

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

   SirumLog::$subroutine_id = "order-items-updated-".sha1(serialize($updated));

    SirumLog::debug(
      "update_order_items: Order Item updated",
      [
          'updated' => $updated,
          'source'  => 'CarePoint',
          'type'    => 'order-items',
          'event'   => 'updated'
      ]
    );

    $item = get_full_item($updated, $mysql, true);

    if ( ! $item) {
      log_error("Updated Item Missing", get_defined_vars());
      continue;
    }

    $changed = changed_fields($updated);

    if ($item['days_dispensed_actual']) {

      freeze_invoice_data($item, $mysql);

      if ($item['days_dispensed_actual'] == $item['days_dispensed_default']) {
        log_info("days_dispensed_actual was set", [$updated, $changed]);
      } else {
        //This already picked up by dispensing_changes in update_orders_cp.php
        //log_error("days_dispensed_default was wrong: $item[days_dispensed_default] >>> $item[days_dispensed_actual]", ['item' => $item, 'updated' => $updated, 'changed' => $changed]);
      }

      if ($item['refills_total'] != $item['refills_dispensed_default']) { //refills_dispensed_actual is not set yet, so use refills_total instead
        log_notice('update_order_items: refills_dispensed changed', $item);
      }

    } else if ($updated['item_added_by'] == 'MANUAL' AND $updated['old_item_added_by'] != 'MANUAL') {

      log_info("Cindy deleted and readded this item", [$updated, $changed]);

    } else if ( ! $item['days_dispensed_default']) {

      log_error("Updated Item has no days_dispensed_default.  Why no days_dispensed_default? GSN added?", get_defined_vars());

    } else {
      log_info("Updated Item No Action", get_defined_vars());
    }

    log_info("update_order_items", get_defined_vars());

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  SirumLog::resetSubroutineId();
}
