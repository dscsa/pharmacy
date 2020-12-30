<?php

require_once 'helpers/helper_days_and_message.php';
require_once 'helpers/helper_full_item.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
require_once 'exports/export_gd_transfer_fax.php';

use Sirum\Logging\SirumLog;

function update_order_items($changes) {

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
  echo $msg;
  log_info("update_order_items: all changes. $msg", [
    'deleted_count' => $count_deleted,
    'created_count' => $count_created,
    'updated_count' => $count_updated
  ]);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  //If just added to CP Order we need to
  //  - determine "days_dispensed_default" and "qty_dispensed_default"
  //  - pend in v2 and save applicable fields
  //  - if first line item in order, find out any other rxs need to be added
  //  - update invoice
  //  - update wc order total
  $loop_timer = microtime(true);
  foreach($changes['created'] as $created) {

    SirumLog::$subroutine_id = "order-items-created-".sha1(serialize($created));

    //This will add/remove and pend/unpend items from the order
    $item = load_full_item($created, $mysql, true);

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

    if ($created['count_lines'] > 1) {
      $warn = ["$item[invoice_number] $item[drug_generic] is a duplicate line", 'created' => $created, 'item' => $item];
      $item = deduplicate_order_items($item, $mssql, $mysql);
      SirumLog::warning($warn[0], $warn);
    }

    if ($item['days_dispensed_actual']) {

      log_error("order_item created but days_dispensed_actual already set.  Most likely an new rx but not part of a new order (days actual is from a previously shipped order) or an item added to order and dispensed all within the time between cron jobs", [$item, $created]);

      SirumLog::debug("Freezing Item as because it's dispensed", $item);
      freeze_invoice_data($item, $mysql);
      continue;
    }

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }
  log_timer('order-items-created', $loop_timer, $count_created);

  $loop_timer = microtime(true);
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

    $item = load_full_item($deleted, $mysql);

    v2_unpend_item($item, $mysql);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }
  log_timer('order-items-deleted', $loop_timer, $count_deleted);

  $loop_timer = microtime(true);
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

    $item = load_full_item($updated, $mysql, true);

    if ( ! $item) {
      log_error("Updated Item Missing", get_defined_vars());
      continue;
    }

    $changed = changed_fields($updated);

    if ($updated['count_lines'] > 1) {
      $warn = ["$item[invoice_number] $item[drug_generic] is a duplicate line", 'updated' => $updated, 'changed' => $changed, 'item' => $item];
      $item = deduplicate_order_items($item, $mssql, $mysql);
      SirumLog::warning($warn[0], $warn);
    }

    if ($item['days_dispensed_actual']) {
      SirumLog::debug("Freezing Item as because it's dispensed and updated", $item);
      freeze_invoice_data($item, $mysql);

      //! $updated['order_date_dispensed'] otherwise triggered twice, once one stage: Printed/Processed and again on stage:Dispensed
      $sig_qty_per_day_actual = round($item['qty_dispensed_actual']/$item['days_dispensed_actual'], 3);

      $mysql->run("
        UPDATE gp_rxs_single SET sig_qty_per_day_actual = $sig_qty_per_day_actual WHERE rx_number = $item[rx_number]
      ");


      if ($item['days_dispensed_actual'] != $item['days_dispensed_default']) {

        log_warning("days_dispensed_default was wrong: $item[days_dispensed_default] >>> $item[days_dispensed_actual]", [
          'item' => $item,
          'updated' => $updated,
          'changed' => $changed
        ]);

        if ( ! $sig_qty_per_day_actual OR $item['sig_qty_per_day_default']*2 < $sig_qty_per_day_actual OR $item['sig_qty_per_day_default']/2 > $sig_qty_per_day_actual) {
          log_error("sig parsing error Updating to Actual Qty_Per_Day '$item[sig_actual]' $item[sig_qty_per_day_default] (default) != $sig_qty_per_day_actual $item[qty_dispensed_actual]/$item[days_dispensed_actual] (actual)", $item);
        }

      } else if (
        $item['qty_dispensed_actual'] != $item['qty_dispensed_default'] OR
        $item['refills_dispensed_actual'] != $item['refills_dispensed_default']
      ) {
        log_alert("days_dispensed_actual same as default but qty or refills changed so invoice needs to be updated", [
          'item' => $item,
          'updated' => $updated,
          'changed' => $changed
        ]);

        //$order = load_full_order($updated);
        //$order = export_gd_update_invoice($order, "update_order_items: refill/qty change upon dispensing", $mysql);
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
log_timer('order-items-updated', $loop_timer, $count_updated);


function deduplicate_order_items($item, $mssql, $mysql) {

  $item['count_lines'] = 1;

  $sql1 = "
    UPDATE gp_order_items SET count_lines = 1 WHERE invoice_number = $item[invoice_number] AND rx_number = $item[rx_number]
  ";

  $res1 = $mysql->run($sql1)[0];

  //DELETE doesn't work with offset so do it in two separate queries
  $sql2 = "
    SELECT
      *
    FROM
      csomline
    JOIN
      cprx ON cprx.rx_id = csomline.rx_id
    WHERE
      order_id  = ".($item['invoice_number']-2)."
      AND rxdisp_id = 0
      AND (
        script_no = $item[rx_number]
        OR '$item[drug_gsns]' LIKE CONCAT('%,', gcn_seqno, ',%')
      )
    ORDER BY
      csomline.add_date ASC
    OFFSET 1 ROWS
  ";

  $res2 = $mssql->run($sql2)[0];

  foreach($res2 as $duplicate) {
    $mssql->run("DELETE FROM csomline WHERE line_id = $duplicate[line_id]");
  }

  log_notice('deduplicate_order_item', [$sql1, $res1, $sql2, $res2]);

  $new_count_items = export_cp_recount_items($item['invoice_number'], $mssql);

  return $item;
}
