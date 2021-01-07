<?php

require_once 'helpers/helper_days_and_message.php';
require_once 'helpers/helper_full_item.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
require_once 'exports/export_gd_transfer_fax.php';

use Sirum\Logging\SirumLog;
use Sirum\Logging\AuditLog;

function update_order_items($changes)
{
    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
    echo $msg;
    SirumLog::info(
        "update_order_items: all changes. {$msg}",
        [
            'deleted_count' => $count_deleted,
            'created_count' => $count_created,
            'updated_count' => $count_updated
        ]
    );

    if (! $count_deleted and ! $count_created and ! $count_updated) {
        return;
    }

    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();
    $orders_updated = [];

    //If just added to CP Order we need to
    //  - determine "days_dispensed_default" and "qty_dispensed_default"
    //  - pend in v2 and save applicable fields
    //  - if first line item in order, find out any other rxs need to be added
    //  - update invoice
    //  - update wc order total
    $loop_timer = microtime(true);
    foreach ($changes['created'] as $created) {
        SirumLog::$subroutine_id = "order-items-created-".sha1(serialize($created));

        $invoice_number = $created['invoice_number'];

        //This will add/remove and pend/unpend items from the order
        $item = load_full_item($created, $mysql);

        //TODO Less hacky way to determine if this addition was already part of the order_created_notice (items_to_add) that went out on thelast go-around
        $in_created_notice = strtotime($item['item_date_added']) - strtotime($item['order_date_added']) < 20*60;

        SirumLog::debug(
          "update_order_items: Order Item created $invoice_number",
          [
            'item'    => $item,
            'created' => $created,
            'in_created_notice' => $in_created_notice,
            'source'  => 'CarePoint',
            'type'    => 'order-items',
            'event'   => 'created'
          ]
        );

        if (!$item) {
            SirumLog::error("Created Item Missing", [ 'created' => $created ]);
            continue;
        }

        if ($created['count_lines'] > 1) {
            $item = deduplicate_order_items($item, $mssql, $mysql);
            SirumLog::warning(
                sprintf(
                    "%s %s is a duplicate line",
                    $invoice_number,
                    $item['drug_generic']
                ),
                [
                    'created' => $created,
                    'item' => $item
                ]
            );
        }

        //We are filling this item and this is an order UPDATE not an order CREATED
        if ($item['days_dispensed_default'] > 0 AND ! $in_created_notice) {

          if ( ! @$orders_updated[$invoice_number])
            $orders_updated[$invoice_number] = [
              'added'   => [],
              'removed' => []
            ];

          $orders_updated[$invoice_number]['added'][] = $item;
        }

        if ($item['days_dispensed_actual']) {
            SirumLog::error(
                "order_item created but days_dispensed_actual already set.
                    Most likely an new rx but not part of a new order (days actual
                    is from a previously shipped order) or an item added to order and
                    dispensed all within the time between cron jobs",
                [ 'item' => $item, 'created' => $created]
            );
            SirumLog::debug("Freezing Item as because it's dispensed", $item);
            $item = set_item_invoice_data($item, $mysql);
            continue;
        }

        //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
    }
    log_timer('order-items-created', $loop_timer, $count_created);

    $loop_timer = microtime(true);
    foreach ($changes['deleted'] as $deleted) {
        SirumLog::$subroutine_id = "order-items-deleted-".sha1(serialize($deleted));

        $invoice_number = $deleted['invoice_number'];

        $item = load_full_item($deleted, $mysql);

        SirumLog::debug(
            "update_order_items: Order Item deleted $invoice_number",
            [
                'deleted' => $deleted,
                'item'    => $item,
                'source'  => 'CarePoint',
                'type'    => 'order-items',
                'event'   => 'deleted'
            ]
        );

        //This item was going to be filled, and the whole order was not deleted
        if ($deleted['days_dispensed_default'] > 0 AND @$deleted['order_date_added']) {

          if ( ! @$orders_updated[$invoice_number])
            $orders_updated[$invoice_number] = [
              'added'   => [],
              'removed' => []
            ];

          $orders_updated[$invoice_number]['removed'][] = array_merge($item, $deleted);
        }

        /*
            WARNING Cannot unpend all items effectively in order-items-deleted loops
            given the current pend group names which are based on order_date_added,
            since the order is likely already deleted here, order_date_added is null
            so you cannot deduce the correct pended group name to find and unpend
        */

       //Only available if item was deleted from an order that is still active
        if (@$deleted['order_date_added']) {
            AuditLog::log(
                sprintf(
                    "Order item % deleted for Rx#%s GSN#%s, Unpending",
                    $item['drug_name'],
                    $item['rx_number'],
                    $item['drug_gsns']
                ),
                $deleted
            );
        }

        $item = v2_unpend_item(
            array_merge($item, $deleted),
            $mysql,
            "order-item-deleted and order still exists"
        );

        /*
            TODO Update Salesforce Order Total & Order Count & Order Invoice
            using REST API or a MYSQL Zapier Integration
         */
    }
    log_timer('order-items-deleted', $loop_timer, $count_deleted);



    //TODO Somehow bundle patients comms if we are adding/removing drugs on next go-around, since order_update_notice would need to be sent again?
    //The above seems like would be tricky so skipping this for now
    if ($orders_updated) {

      $reason = "helper_full_fields: send_updated_order_communications for ".count($orders_updated)." orders";

      SirumLog::debug(
        $reason,
        [
          'orders_updated'  => $orders_updated,
        ]
      );

      foreach($orders_updated as $invoice_number => $updates) {

        $order  = load_full_order(['invoice_number' => $invoice_number], $mysql);
        $groups = group_drugs($order, $mysql);

        send_updated_order_communications($groups, $updates['added'], $updates['removed']);
      }
    }
    log_timer('order-items-updated', $loop_timer, $count_updated);

    $loop_timer = microtime(true);
    //If just updated we need to
    //  - see which fields changed
    //  - think about what needs to be updated based on changes
    foreach ($changes['updated'] as $updated) {
        SirumLog::$subroutine_id = "order-items-updated-".sha1(serialize($updated));

        $changed = changed_fields($updated);

        SirumLog::debug(
            "update_order_items: Order Item updated",
            [
                'updated' => $updated,
                'changed' => $changed,
                'source'  => 'CarePoint',
                'type'    => 'order-items',
                'event'   => 'updated'
            ]
        );

        $item = load_full_item($updated, $mysql);

        if (! $item) {
            SirumLog::error(
                "Updated Item Missing",
                [
                    'updated' => $updated,
                    'changed' => $changed
                ]
            );
            continue;
        }

        if ($updated['count_lines'] > 1) {
            SirumLog::warning(
                sprintf(
                    "%s %s is a duplicate line",
                    $item['invoice_number'],
                    $item['drug_generic']
                ),
                [
                    'updated' => $updated,
                    'changed' => $changed,
                    'item' => $item
                ]
            );
            $item = deduplicate_order_items($item, $mssql, $mysql);
        }

        if ($item['days_dispensed_actual']) {
            SirumLog::debug("Freezing Item as because it's dispensed and updated", $item);

            $item = set_item_invoice_data($item, $mysql);

            AuditLog::log(
                sprintf(
                    "Freezing item %s for Rx#%s GSN#%s because it is dispensed and updated",
                    $item['drug_name'],
                    $item['rx_number'],
                    $item['drug_gsns']
                ),
                $updated
            );

            //! $updated['order_date_dispensed'] otherwise triggered twice, once one
            //! stage: Printed/Processed and again on stage:Dispensed
            $sig_qty_per_day_actual = round($item['qty_dispensed_actual']/$item['days_dispensed_actual'], 3);

            $mysql->run("UPDATE gp_rxs_single
                    SET sig_qty_per_day_actual = {$sig_qty_per_day_actual}
                    WHERE rx_number = {$item['rx_number']}");

            if (! $sig_qty_per_day_actual
                or $item['sig_qty_per_day_default']*2 < $sig_qty_per_day_actual
                 or $item['sig_qty_per_day_default']/2 > $sig_qty_per_day_actual) {
                SirumLog::error(
                    sprintf(
                        "sig parsing error Updating to Actual Qty_Per_Day '%s' %s (default) != %s %s/%s (actual)",
                        $item['sig_actual'],
                        $item['sig_qty_per_day_default'],
                        $sig_qty_per_day_actual,
                        $item['qty_dispensed_actual'],
                        $item['days_dispensed_actual']
                    ),
                    [ 'item' => $item ]
                );
            }

            if ($item['days_dispensed_actual'] != $item['days_dispensed_default']) {
                SirumLog::warning(
                    sprintf(
                        "days_dispensed_default was wrong: %s >>> %s",
                        $item['days_dispensed_default'],
                        $item['days_dispensed_actual']
                    ),
                    [
                        'item'    => $item,
                        'updated' => $updated,
                        'changed' => $changed
                    ]
                );
            } elseif ($item['qty_dispensed_actual'] != $item['qty_dispensed_default'] or
                        $item['refills_dispensed_actual'] != $item['refills_dispensed_default']
            ) {
                SirumLog::warning(
                    sprintf(
                        "days_dispensed_actual same as default but qty or refills changed",
                        $item['days_dispensed_default'],
                        $item['days_dispensed_actual']
                    ),
                    [
                        'item'    => $item,
                        'updated' => $updated,
                        'changed' => $changed
                    ]
                );
            }
        } elseif ($updated['item_added_by'] == 'MANUAL' and $updated['old_item_added_by'] != 'MANUAL') {
            SirumLog::info(
                "Cindy deleted and readded this item",
                [
                    'updated' => $updated,
                    'changed' => $changed
                ]
            );
        } elseif (! $item['days_dispensed_default']) {
            SirumLog::warning(
                "Updated Item has no days_dispensed_default.  Why no days_dispensed_default? GSN added?",
                [
                    'item'    => $item,
                    'updated' => $updated,
                    'changed' => $changed
                ]
            );
        } else {
            SirumLog::info(
                "Updated Item No Action",
                [
                    'item'    => $item,
                    'updated' => $updated,
                    'changed' => $changed
                ]
            );
        }

        /* TODO Update Salesforce Order Total & Order Count & Order Invoice
           using REST API or a MYSQL Zapier Integration
         */
    }
    log_timer('order-items-updated', $loop_timer, $count_updated);

    SirumLog::resetSubroutineId();
}



function deduplicate_order_items($item, $mssql, $mysql)
{
    $item['count_lines'] = 1;

    $sql1 = "UPDATE gp_order_items
                SET count_lines = 1
                WHERE invoice_number = $item[invoice_number]
                    AND rx_number = $item[rx_number]";

    $res1 = $mysql->run($sql1)[0];

    //DELETE doesn't work with offset so do it in two separate queries
    $sql2 = "SELECT
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

    foreach ($res2 as $duplicate) {
        $mssql->run("DELETE FROM csomline WHERE line_id = {$duplicate['line_id']}");
    }

    SirumLog::notice(
        'deduplicate_order_item',
        [
            'sql'  => $sql1,
            'res1' => $res1,
            'sql2' => $sql2,
            'res2' => $res2
        ]
    );

    $new_count_items = export_cp_recount_items($item['invoice_number'], $mssql);

    return $item;
}
