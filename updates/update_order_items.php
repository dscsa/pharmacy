<?php

require_once 'helpers/helper_days_and_message.php';
require_once 'helpers/helper_full_item.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
require_once 'exports/export_gd_transfer_fax.php';

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

use GoodPill\Utilities\Timer;
use GoodPill\DataModels\GoodPillOrder;


/**
 * Handle all the possible Item updates.  it will go through each type of change
 * and proccess the individual change.
 * @param  array $changes  An array of arrays with deledted, created, and
 *      updated elements
 * @return void
 */
function update_order_items($changes) : void
{
    // Make sure we have some data
    $change_counts = [];
    foreach (array_keys($changes) as $change_type) {
        $change_counts[$change_type] = count($changes[$change_type]);
    }

    if (array_sum($change_counts) == 0) {
        return;
    }

    GPLog::info(
        "update_order_items: changes",
        $change_counts
    );

    $orders_updated = [];

    GPLog::notice('data-update-order-items', $changes);

    if (isset($changes['created'])) {
        Timer::start('update.order.items.created');
        foreach ($changes['created'] as $created) {
            order_item_created($created, $orders_updated);
        }
        Timer::stop('update.order.items.created');
    }

    if (isset($changes['deleted'])) {
        Timer::start('update.order.items.deleted');
        foreach ($changes['deleted'] as $deleted) {
            order_item_deleted($deleted, $orders_updated);
        }
        Timer::stop('update.order.items.deleted');
    }

    if (! empty($orders_updated)) {
        //TODO Somehow bundle patients comms if we are adding/removing drugs on next
        //TODO  go-around, since order_update_notice would need to be sent again?
        //The above seems like would be tricky so skipping this for now
        $reason = "update_order_items: determining order updates for ".count($orders_updated)." orders";

        GPLog::debug(
            $reason,
            [
              'orders_updated'  => $orders_updated,
            ]
        );

        handle_adds_and_removes($orders_updated);
    }

    if (isset($changes['updated'])) {
        Timer::start('update.order.items.updated');
        foreach ($changes['updated'] as $updated) {
            order_item_updated($updated);
        }
        Timer::stop('update.order.items.updated');
    }
}


/*

    Change Handlers

 */


/**
 * Proccess an and item that has been added to an order.
 *
 * @param  array $created  The base data that we need for the order_item
 * @return false|array     If the Process works, we will return the original
 *      order otherwise we will return false
 */
function order_item_created(array $created, array &$orders_updated) : ?array
{
    //If just added to CP Order we need to
    //  - determine "days_dispensed_default" and "qty_dispensed_default"
    //  - pend in v2 and save applicable fields
    //  - if first line item in order, find out any other rxs need to be added
    //  - update invoice
    //  - update wc order total

    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    GPLog::$subroutine_id = "order-items-created-".sha1(serialize($created));
    GPLog::info("data-order-items-created", ['created' => $created]);

    $invoice_number = $created['invoice_number'];

    $GPOrder = new GoodPillOrder(['invoice_number' => $invoice_number]);
    if ($GPOrder->isShipped()) {
        GPLog::alert(
            "Trying to add an item to an order that has already shipped",
            [ 'created' => $created]
        );
    }

    //This will add/remove and pend/unpend items from the order
    $item = load_full_item($created, $mysql);

    if (!$item) {
        GPLog::critical("Created Item Missing", [ 'created' => $created ]);
        return null;
    }

    /*
        TODO Less hacky way to determine if this addition was already part of the
        order_created_notice (items_to_add) that went out on thelast go-around
        One idea could be an patient_notice = UPDATED|REMOVED|CREATED on orders
         or order_items table that we could check to see if it was sent or not
        Another idea would be to have the user_id be different for the drugs we
        added to a created order and only send an "update" if its not that user
    */
    // Minutes is just trial-and-error.  10 to detect a new order, 10 minutes to
    // sync drugs to the order, 0-10 minutes for buffer if the script runs long
    //  and misses and cycles.

    // If its added by HL7 (?) it is not a sync so you can subtract 10mins.
    // HL7 (0-20min), non HL7 (0-30min) 57258 should have sent updated and only
    // took 22 minutes.
    $minutes_between_added = strtotime($item['item_date_added']) - strtotime($item['order_date_added']);
    $allowable_time        = ($item['item_added_by'] == "HL7" ? 20 : 30) * 60;
    $in_created_notice     = $minutes_between_added <= $allowable_time;

    GPLog::debug(
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

    if ($created['count_lines'] > 1) {
        $item = deduplicate_order_items($item);
        GPLog::warning(
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
    if ($item['days_dispensed_default'] > 0 and ! $in_created_notice) {
        if (! isset($orders_updated[$invoice_number])) {
            $orders_updated[$invoice_number] = [
                'added'   => [],
                'removed' => []
            ];
        }

        $orders_updated[$invoice_number]['added'][] = $item;
    }

    if ($item['days_dispensed_actual']) {
        GPLog::error(
            "order_item created but days_dispensed_actual already set.
                Most likely an new rx but not part of a new order (days actual
                is from a previously shipped order) or an item added to order and
                dispensed all within the time between cron jobs",
            [ 'item' => $item, 'created' => $created]
        );
        GPLog::debug("Freezing Item because it's dispensed", $item);
        $item = set_item_invoice_data($item, $mysql);
        return null;
    }

    GPLog::resetSubroutineId();

    return $created;
}

/**
 * Proccess an and item that has been deleted from an order.
 *
 * @param  array $deleted  The base data that we need for the order_item
 * @return false|array     If the Process works, we will return the original
 *      order otherwise we will return false
 */
function order_item_deleted(array $deleted, array &$orders_updated) : ?array
{
    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    GPLog::$subroutine_id = "order-items-deleted-".sha1(serialize($deleted));
    GPLog::info("data-order-items-deleted", ['deleted' => $deleted]);

    $invoice_number = $deleted['invoice_number'];

    $GPOrder = new GoodPillOrder(['invoice_number' => $invoice_number]);
    if ($GPOrder->isShipped()) {
        GPLog::alert(
            "Trying to delete an item to an order that has already shipped",
            [ 'deleted' => $deleted]
        );
    }

    $item = load_full_item($deleted, $mysql);

    GPLog::debug(
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
    if ($deleted['days_dispensed_default'] > 0 and @$item['order_date_added']) {
        if (! isset($orders_updated[$invoice_number])) {
            $orders_updated[$invoice_number] = [
                'added'   => [],
                'removed' => []
            ];
        }

        $orders_updated[$invoice_number]['removed'][] = array_merge($item, $deleted);
    }

    /*
        TODO Update Salesforce Order Total & Order Count & Order Invoice
        using REST API or a MYSQL Zapier Integration
     */

    GPLog::resetSubroutineId();
    return $deleted;
}

/**
 * Proccess an and item that has been deleted from an order.
 *
 * @param  array $updated  The base data that we need for the order_item
 * @return false|array     If the Process works, we will return the original
 *      $updated otherwise we will return false
 */
function order_item_updated(array $updated) : ?array
{
    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    GPLog::$subroutine_id = "order-items-updated-".sha1(serialize($updated));
    GPLog::info("data-order-items-updated", ['updated' => $updated]);

    $changed = changed_fields($updated);

    $GPOrder = new GoodPillOrder(['invoice_number' => $updated['invoice_number']]);
    if ($GPOrder->isShipped()) {
        GPLog::alert(
            "Trying to change an item on an order that has already shipped",
            [ 'updated' => $updated]
        );
    }

    GPLog::debug(
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
        GPLog::critical(
            "Updated Item Missing",
            [
                'updated' => $updated,
                'changed' => $changed
            ]
        );
        return null;
    }

    if ($updated['count_lines'] > 1) {
        GPLog::warning(
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
        $item = deduplicate_order_items($item);
    }

    if ($item['days_dispensed_actual']) {
        GPLog::debug("Freezing Item as because it's dispensed and updated", $item);

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

        if (
            ! $sig_qty_per_day_actual
            or $item['sig_qty_per_day_default']*2 < $sig_qty_per_day_actual
            or $item['sig_qty_per_day_default']/2 > $sig_qty_per_day_actual
        ) {
            GPLog::error(
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
            GPLog::warning(
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
        } elseif (
            $item['qty_dispensed_actual'] != $item['qty_dispensed_default']
            || $item['refills_dispensed_actual'] != $item['refills_dispensed_default']
        ) {
            GPLog::warning(
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
    } elseif (
        $updated['item_added_by'] == 'MANUAL'
        && $updated['old_item_added_by'] != 'MANUAL'
    ) {
        GPLog::info(
            "Cindy deleted and readded this item",
            [
                'updated' => $updated,
                'changed' => $changed
            ]
        );
    } elseif (! $item['days_dispensed_default']) {
        GPLog::warning(
            "Updated Item has no days_dispensed_default.  Why no days_dispensed_default? GSN added?",
            [
                'item'    => $item,
                'updated' => $updated,
                'changed' => $changed
            ]
        );
    } else {
        GPLog::info(
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

    GPLog::resetSubroutineId();
    return $updated;
}


/*

    Supporting functions

 */

/**
 * Proccess all the add and remove items so we don't overwhelm the patient
 *
 * @param  $array $orders_updated All the items that have been modified
 *      grouped by invoice
 * @return void
 */
function handle_adds_and_removes(array $orders_updated) : void
{
    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    foreach ($orders_updated as $invoice_number => $updates) {
        $order  = load_full_order(['invoice_number' => $invoice_number], $mysql);
        $groups = group_drugs($order, $mysql);

        $items = [];
        $add_item_names    = [];
        $remove_item_names = [];

        foreach ($updates['added'] as $item) {
            $items[$item['drug']] = $item;
            $add_item_names[] = $item['drug'];
        }

        foreach ($updates['removed'] as $item) {
            $items[$item['drug']] = $item;
            $remove_item_names[] = $item['drug'];
        }

        // an rx_number was swapped (e.g best_rx_number used instead) same
        // drug may have been added and removed
        // at same time so we need to remove the intersection
        $added_deduped    = array_diff($add_item_names, $remove_item_names);

         /*
            something might have been removed as a duplicate, but we don't want
            to say it was "removed" if drug is still in the order so we remove
            all FILLED (rather than just the added)
         */
        $removed_deduped  = array_diff($remove_item_names, $groups['FILLED']);

        GPLog::warning(
            "update_order_items: order $invoice_number updated",
            [
                'invoice_number'    => $invoice_number,
                'updates'           => $updates,
                'add_item_names'    => $add_item_names,
                'remove_item_names' => $remove_item_names,
                'added_deduped'     => $added_deduped,
                'removed_deduped'   => $removed_deduped,
                'groups'            => $groups
            ]
        );

        /*
            We had issues in orders like 55256 Apixaban where the rx_number
            was swapped this pended items for the new rx in order-items-created
            BUT then unpended BOTH Rxs in order-items-deleted.  This is because
            v2 unpend works by drug name, which was the same for the two Rxs.
            So for rx_number swaps, let's only unpend removals that not in added
         */

        /*
            NOTE Cannot unpend all items effectively in order-items-deleted loops
            given the current pend group names which are based on order_date_added,
            since the order is likely already deleted here, order_date_added is null
            so you cannot deduce the correct pended group name to find and unpend.  this
            conditional is currently handled when adding items to $updates['removed']
        */

        //Only available if item was deleted from an order that is still active
        foreach ($removed_deduped as $drug_name) {
            $item = $items[$drug_name];

            AuditLog::log(
                sprintf(
                    "Order item %s deleted for Rx#%s GSN#%s, Unpending",
                    $item['drug_name'],
                    $item['rx_number'],
                    $item['drug_gsns']
                ),
                $item
            );

            $item = v2_unpend_item(
                array_merge($item),
                $mysql,
                "order-item-deleted and order still exists"
            );
        }

        send_updated_order_communications($groups, $added_deduped, $removed_deduped);
    }
}

/**
 * Remove any duplicate items that are attached to an order
 * @param  array $item  The item we are working to clear
 * @return array        The item and any modifications
 *
 * @todo switch the mssql to a pdo bind param command
 */
function deduplicate_order_items(array $item) : array
{
    $goodpill_db = GoodPill\Storage\GoodPill::getConnection();
    $mssql       = new Mssql_Cp();

    if (empty($item['rx_number'])) {
        return $item;
    }


    $item['count_lines'] = 1;


    $sql1 = "UPDATE gp_order_items
                SET count_lines = 1
                WHERE invoice_number = :invoice_number
                    AND rx_number = :rx_number";

    $pdo = $goodpill_db->prepare($sql1);
    $pdo->bindParam(':invoice_number', $item['invoice_number'], \PDO::PARAM_INT);
    $pdo->bindParam(':rx_number', $item['rx_number'], \PDO::PARAM_INT);
    $pdo->execute();

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
                    script_no = {$item['rx_number']}
                    OR '{$item['drug_gsns']}' LIKE CONCAT('%,', gcn_seqno, ',%')
                  )
                ORDER BY
                  csomline.add_date ASC
                OFFSET 1 ROWS";

    $res2 = $mssql->run($sql2)[0];

    foreach ($res2 as $duplicate) {
        $mssql->run("DELETE FROM csomline WHERE line_id = {$duplicate['line_id']}");
    }

    GPLog::notice(
        'deduplicate_order_item',
        [
            'sql'  => $sql1,
            'sql2' => $sql2,
            'res2' => $res2
        ]
    );

    $new_count_items = export_cp_recount_items($item['invoice_number'], $mssql);

    return $item;
}
