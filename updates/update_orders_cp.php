<?php

require_once 'helpers/helper_full_order.php';
require_once 'helpers/helper_payment.php';
require_once 'helpers/helper_syncing.php';
require_once 'helpers/helper_communications.php';
require_once 'exports/export_wc_orders.php';
require_once 'exports/export_cp_orders.php';
require_once 'exports/export_v2_order.php';
require_once 'helpers/helper_try_catch_log.php';

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

use GoodPill\Utilities\Timer;

/**
 * The general main function to proccess the individual change arrays
 * @param  array $changes  An array of arrays with deledted, created, and
 *      updated elements
 * @return void
 */
function update_orders_cp(array $changes) : void
{
    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";

    GPLog::info(
        "update_orders_cp: all changes. {$msg}",
        [
            'deleted_count' => $count_deleted,
            'created_count' => $count_created,
            'updated_count' => $count_updated
        ]
    );

    CliLog::info($msg);

    if ($count_deleted + $count_created + $count_updated == 0) {
        return;
    }

    GPLog::notice('data-update-orders-cp', $changes);

    $mysql = new Mysql_Wc();

    Timer::start("update.patients.cp.created");
    foreach ($changes['created'] as $created) {
        helper_try_catch_log('cp_order_created', $created);
    }
    Timer::stop("update.patients.cp.created");

    Timer::start("update.patients.cp.deleted");
    foreach ($changes['deleted'] as $deleted) {
        helper_try_catch_log('cp_order_deleted', $deleted);
    }
    Timer::stop("update.patients.cp.deleted");

    Timer::start("update.patients.cp.updated");
    foreach ($changes['updated'] as $i => $updated) {
        helper_try_catch_log('cp_order_updated', $updated);
    }
    Timer::stop("update.patients.cp.updated");
    GPLog::resetSubroutineId();
}

/*

    Change Handlers

 */

/**
 * Handle a Carepoint order that was created
 *
 * @param  array  $created The data that represents the created element
 * @return null|array      NULL on early return or the $created data on full execution
 */
function cp_order_created(array $created) : ?array
{
    GPLog::$subroutine_id = "orders-cp-created-".sha1(serialize($created));
    GPLog::info("data-orders-cp-created", ['created' => $created]);

    $mysql     = new Mysql_Wc();
    $duplicate = get_current_orders($mysql, ['patient_id_cp' => $created['patient_id_cp']]);

    GPLog::debug(
        "get_full_order: Carepoint Order created ". $created['invoice_number'],
        [
            'invoice_number' => $created['invoice_number'],
            'created'        => $created,
            'duplicate'      => $duplicate,
            'source'         => 'CarePoint',
            'type'           => 'orders',
            'event'          => 'created'
        ]
    );

    if ($created['order_date_returned']) {
        export_wc_return_order($created['invoice_number']);
        return null;
    }

    if (count($duplicate) > 1
        && $duplicate[0]['invoice_number'] != $created['invoice_number']
        && (
          ! is_webform($created)
          || is_webform($duplicate[0])
        )) {
        GPLog::warning(
            sprintf(
                "Created Carepoint Order Seems to be a duplicate %s >>> %s",
                $duplicate[0]['invoice_number'],
                $created['invoice_number']
            ),
            [
                'invoice_number' => $created['invoice_number'],
                'created' => $created,
                'duplicate' => $duplicate
            ]
        );

        AuditLog::log(
            "Duplicate invoice found in Carepoint and deleted",
            $created
        );

        /*
            Not sure what we should do here. Delete it? Instance where
            current order doesn't have all drugs, so patient/staff add a
            second order with the drug.  Merge orders?
         */
        $order = export_v2_unpend_order(
            $created,
            $mysql,
            sprintf(
                "Duplicate order  %s >>> %s",
                $duplicate[0]['invoice_number'],
                $created['invoice_number']
            )
        );

        export_cp_merge_orders(
            $created['invoice_number'],
            $duplicate[0]['invoice_number']
        );

        if (is_webform($created)) {
            export_wc_cancel_order(
                $created['invoice_number'],
                "Duplicate of {$duplicate[0]['invoice_number']}"
            );
        } else {
            export_wc_delete_order(
                $created['invoice_number'],
                "Duplicate of {$duplicate[0]['invoice_number']}"
            );
        }

        return null;
    }

    // Overwrite Rx Messages everytime a new order created otherwise
    // same message would stay for the life of the Rx
    $order = load_full_order($created, $mysql, true);

    if (! $order) {
        GPLog::debug(
            "Created Order Missing.  Most likely because cp order has liCount >
              0 even though 0 items in order.  If correct, update liCount in CP to 0",
            ['order' => $order]
        );
        return null;
    }

    GPLog::debug(
        "Order found for created order",
        [
            'invoice_number' => $order[0]['invoice_number'],
            'order'          => $order,
            'created'        => $created
        ]
    );

    //TODO Add Special Case for Webform Transfer [w/ Note] here?

    if ($order[0]['order_date_dispensed']) {
        $reason = "update_orders_cp: dispened/shipped/returned order being readded";

        export_wc_create_order($order, $reason);
        $order = export_gd_publish_invoice($order);
        export_gd_print_invoice($order[0]['invoice_number']);
        AuditLog::log(
            sprintf(
                "Order %s marked as dispensed",
                $created['invoice_number']
            ),
            $created
        );

        GPLog::debug(
            'Dispensed/Shipped/Returned order is missing and is being added back to the wc and gp tables',
            [
                'invoice_number' => $order[0]['invoice_number'],
                'order'          => $order
            ]
        );

        return null;
    }

    /*
       Patient communication that we are cancelling their order
       examples include:
            NEEDS FORM
            ORDER HOLD WAITING FOR RXS
            TRANSFER OUT OF ALL ITEMS
            ACTION PATIENT OFF AUTOFILL
     */

    if ($order[0]['count_filled'] == 0
        and $order[0]['count_to_add'] == 0
        and ! is_webform_transfer($order[0])) {
        AuditLog::log(
            sprintf(
                "Order %s has no drugs to fill, so it will be removed",
                $created['invoice_number']
            ),
            $created
        );

        GPLog::warning(
            "update_orders_cp: created. no drugs to fill. removing order
            {$order[0]['invoice_number']}. Can we remove the v2_unpend_order
            below because it get called on the next run?",
            [
                'invoice_number'  => $order[0]['invoice_number'],
                'count_filled'    => $order[0]['count_filled'],
                'count_items'     => $order[0]['count_items'],
                'count_to_add'    => $order[0]['count_to_add'],
                'count_to_remove' => $order[0]['count_to_remove'],
                'order'           => $order
            ]
        );

        if ($order[0]['count_items'] - $order[0]['count_to_remove']) {
            // Find the item that wasn't removed, but we aren't filling
            // These values are transient and
            // TODO Why is this happening
            GPLog::critical(
                "update_orders_cp: created. canceling order, but is their a manually added item that we should keep?"
            );
        }

        // TODO Remove/Cancel WC Order Here Or Is this done on next go-around?

        /*
           TODO Why do we need to explicitly unpend?  Deleting an order in CP
           should trigger the deleted loop on next run, which should unpend
           But it seemed that this didn't happen for Order 53684
         */
        if (! $order[0]['pharmacy_name']) {
            $reason = 'Needs Registration';
            $groups = group_drugs($order, $mysql);
            needs_form_notice($groups);
        } elseif ($order[0]['order_status'] == "Surescripts Authorization Approved") {
            $reason = "Surescripts Approved {$order[0]['drug_generic']} {$order[0]['rx_number']} {$order[0]['rx_message_key']}";
        } elseif ($order[0]['order_status'] == "Surescripts Authorization Denied") {
            $reason = "Surescripts Denied {$order[0]['drug_generic']} {$order[0]['rx_number']} {$order[0]['rx_message_key']}";
        } elseif ($order[0]['count_items'] == 0) {
            $reason = 'Created Empty';
        } elseif ($order[0]['count_items'] == 1) {
            $reason = "1 Rx Removed {$order[0]['drug_generic']} {$order[0]['rx_number']} {$order[0]['rx_message_key']}";
        } elseif ($order[0]['count_items'] == 2) {
            $reason = "2 Rxs Removed {$order[0]['drug_generic']} {$order[0]['rx_message_key']}; {$order[1]['drug_generic']} {$order[1]['rx_message_key']}";
        } else { //Not enough space to put reason if >1 drug removed. using 0-index depends on the current sort order based on item_date_added.
            $reason = $order[0]['count_items'].' Rxs Removed';
        }

        $order  = export_v2_unpend_order($order, $mysql, $reason);
        export_cp_remove_order($order[0]['invoice_number'], $reason);

        if (is_webform($order[0])) {
            export_wc_cancel_order($order[0]['invoice_number'], $reason);
        } else {
            export_wc_delete_order($order[0]['invoice_number'], $reason);
        }
        return null;
    }

    if (is_webform_transfer($order[0])) {
        return null; // order hold notice not necessary for transfers
    }

    //Needs to be called before "$groups" is set
    $order  = sync_to_date($order, $mysql);
    $groups = group_drugs($order, $mysql);

    if ($order[0]['count_filled'] > 0 or $order[0]['count_to_add'] > 0) {

        // This is not necessary if order was created by webform, which then
        // created the order in Guardian
        // "order_source": "Webform eRX/Transfer/Refill [w/ Note]"
        if (! is_webform($order[0])) {
            GPLog::debug(
                "Creating order ".$order[0]['invoice_number']." in woocommerce because source is not the Webform and looks like there are items to fill",
                [
                  'invoice_number' => $order[0]['invoice_number'],
                  'source'         => $order[0]['order_source'],
                  'order'          => $order,
                  'groups'         => $groups
                ]
            );

            export_wc_create_order($order, "update_orders_cp: created");
        }
    }

    return $created;
    GPLog::resetSubroutineId();
}

/**
 * Handle an order that has been deleted from guardian
 * @param  array  $deleted The base set of data that needs to be deleted
 * @return null|array      Null on an early return, the $deleted data on full run
 */
function cp_order_deleted(array $deleted) : ?array
{
    /*
     * If just deleted from CP Order we need to
     *  - set "days_dispensed_default" and "qty_dispensed_default" to 0
     *  - unpend in v2 and save applicable fields
     *  - if last line item in order, find out any other rxs need to be removed
     *  - update invoice
     *  - update wc order total
     */
    GPLog::$subroutine_id = "orders-cp-deleted-".sha1(serialize($deleted));
    GPLog::info("data-orders-cp-deleted", ['deleted' => $deleted]);

    GPLog::debug(
        "update_orders_cp: carepoint order {$deleted['invoice_number']} has been deleted",
        [
            'source'         => 'CarePoint',
            'event'          => 'deleted',
            'type'           => 'orders',
            'invoice_number' => $deleted['invoice_number'],
            'deleted'        => $deleted
        ]
    );

    export_cp_remove_items($deleted['invoice_number']);
    export_gd_delete_invoice($deleted['invoice_number']);

    GPLog::info(
        'update_orders_cp deleted: unpending all items',
        [ 'deleted' => $deleted ]
    );

    v2_unpend_order_by_invoice($deleted['invoice_number'], $deleted);

    AuditLog::log(
        sprintf(
            "All items for order %s have been unpended",
            $deleted['invoice_number']
        ),
        $deleted
    );

    $replacement = get_current_orders((new Mysql_Wc()), ['patient_id_cp' => $deleted['patient_id_cp']]);

    if ($replacement) {
        AuditLog::log(
            sprintf(
                "Order %s was deleted in CarePoint but there is another Order %s",
                $deleted['invoice_number'],
                $replacement[0]['invoice_number']
            ),
            $deleted
        );

        GPLog::warning(
            'update_orders_cp deleted: their appears to be a replacement',
            [
                'deleted'     => $deleted,
                'replacement' => $replacement,
                'groups'      => $groups,
                'patient'     => $patient
            ]
        );

        // TODO:BEN Add an if here to see if we even have a wp to delete
        export_wc_delete_order(
            $deleted['invoice_number'],
            "update_orders_cp: cp order deleted but replacement"
        );

        return null;
    }

    $reason = implode(
        " ",
        [
            $deleted['invoice_number'],
            $deleted['order_stage_cp'],
            $deleted['order_stage_wc'],
            $deleted['order_source'],
            $deleted['order_note'],
            json_encode($deleted)
        ]
    );

    if ($deleted['count_filled'] > 0) {
        AuditLog::log(
            sprintf(
                "Order %s was manually deleted in CarePoint and canceled in WooCommerce",
                $deleted['invoice_number']
            ),
            $deleted
        );

        export_wc_cancel_order(
            $deleted['invoice_number'],
            "update_orders_cp: cp order manually cancelled $reason"
        );
        // We passed in $deleted because there is not $order to make $groups
        order_cancelled_notice($deleted, $groups);
    } elseif (is_webform($deleted)) {
        AuditLog::log(
            sprintf(
                "Order %s was deleted in CarePoint and canceled in WooCommerce",
                $deleted['invoice_number']
            ),
            $deleted
        );
        export_wc_cancel_order(
            $deleted['invoice_number'],
            "update_orders_cp: cp order webform cancelled $reason"
        );

        // We passed in $deleted because there is not $order to make $groups
        order_cancelled_notice($deleted, []);
    } else {
        AuditLog::log(
            sprintf(
                "Order %s was deleted in CarePoint and WooCommerce",
                $deleted['invoice_number']
            ),
            $deleted
        );
        export_wc_delete_order(
            $deleted['invoice_number'],
            "update_orders_cp: cp order deleted $reason"
        );
    }

    GPLog::resetSubroutineId();
    return $deleted;
}

/**
 * Handle a Carepoint order that was created
 *
 * @param  array  $updated The data that represents the created element
 * @return null|array      NULL on early return or the $updated data on full execution
 */
function cp_order_updated(array $updated) : ?array
{
    //If just updated we need to
    //  - see which fields changed
    //  - think about what needs to be updated based on changes
    GPLog::$subroutine_id = "orders-cp-updated-".sha1(serialize($updated));
    GPLog::info("data-orders-cp-updated", ['updated' => $updated]);

    $changed = changed_fields($updated);

    GPLog::debug(
        "Carepoint Order {$updated['invoice_number']} has been updated",
        [
            'source'         => 'CarePoint',
            'event'          => 'updated',
            'invoice_number' => $updated['invoice_number'],
            'type'           => 'orders',
            'updated'        => $updated,
            'changed'        => $changed
        ]
    );

    $stage_change_cp = $updated['order_stage_cp'] != $updated['old_order_stage_cp'];

    GPLog::notice(
        sprintf(
            "Updated Orders Cp: %s",
            $updated['invoice_number']
        ),
        [ 'changed' => $changed]
    );

    $mysql  = new Mysql_Wc();
    $order  = load_full_order($updated, $mysql);
    $groups = group_drugs($order, $mysql);

    if (!$order) {
        GPLog::error("Updated Order Missing", [ 'order' => $order ]);
        return null;
    }

    GPLog::debug(
        "Order found for updated order",
        [
            'invoice_number'     => $order[0]['invoice_number'],
            'order'              => $order,
            'updated'            => $updated,
            'order_date_shipped' => $updated['order_date_shipped'],
            'stage_change_cp'    => $stage_change_cp
        ]
    );

    if ($stage_change_cp and $updated['order_date_returned']) {
        AuditLog::log(
            sprintf(
                "Order %s has been returned",
                $updated['invoice_number']
            ),
            $updated
        );

        GPLog::warning(
            'Confirm this order was returned! cp_order with tracking number was deleted, but we keep it in gp_orders and in wc',
            [ 'updated' => $updated ]
        );

        //TODO Patient Communication about the return?

        export_wc_return_order($order[0]['invoice_number']);

        return null;
    }

    GPLog::notice(
        "Order Changed.  Has it shipped or dispensed",
        [
            'State Changed' => $stage_change_cp,
            'Dispensed'     => ($updated['order_date_dispensed'] != $updated['old_order_date_dispensed']),
            'Shipped'       => ($updated['order_date_shipped'] != $updated['old_order_date_shipped'])
        ]
    );

    if (
        $stage_change_cp
        && (
            $updated['order_date_shipped']
            || $updated['order_date_dispensed']
        )
    ) {
        if ($updated['order_date_dispensed'] != $updated['old_order_date_dispensed']) {
            AuditLog::log(
                sprintf(
                    "Order %s was dispensed at %s",
                    $updated['invoice_number'],
                    $updated['order_date_dispensed']
                ),
                $updated
            );
            $reason = "update_orders_cp updated: Updated Order Dispensed ".$updated['invoice_number'];
            $order = helper_update_payment($order, $reason, $mysql);
            $invoice_doc_id = export_gd_create_invoice($order[0]["invoice_number"]);

            // If we have an invoice, lets finish printing it
            if ($invoice_doc_id) {
                // We didn't get a new doc_id so we need to run away
                for ($i = 0; $i < count($order); $i++) {
                    $order[$i]['invoice_doc_id'] = $invoice_doc_id;
                }

                $order = export_gd_publish_invoice($order[0]["invoice_number"]);
                export_gd_print_invoice($order[0]['invoice_number']);
            } else {
                GPLog::error("Failed to generate a google invoice for {$order[0]['invoice_number']}");
            }

            send_dispensed_order_communications($groups);
            GPLog::notice($reason, [ 'order' => $order ]);
        }

        if ($updated['order_date_shipped'] != $updated['old_order_date_shipped']) {
            AuditLog::log(
                sprintf(
                    "Order %s was shipped at %s.  The tracking number is %s",
                    $updated['order_date_shipped'],
                    $updated['invoice_number'],
                    $order[0]['tracking_number']
                ),
                $updated
            );

            GPLog::notice("Updated Order Shipped Started", [ 'order' => $order ]);
            $order = export_v2_unpend_order($order, $mysql, "Order Shipped");
            export_wc_update_order_status($order); //Update status from prepare to shipped
            export_wc_update_order_metadata($order);
            send_shipped_order_communications($groups);
        }

        return null;
    }

    /*
        We should be able to delete wc-confirm-* from CP queue without
        triggering an order cancel notice
    */
    // count_items may already be 0 on a deleted order that had items e.g 33840
    if ($order[0]['count_filled'] == 0 and $order[0]['count_nofill'] == 0) {
        GPLog::warning(
            "update_orders_cp updated: no_rx_notice count_filled == 0 AND count_nofill == 0",
            [
                'updated' => $updated,
                'groups'  => $groups
            ]
        );
        no_rx_notice($updated, $groups);
        return null;
    }

    /*
        Patient communication that we are cancelling their order
        examples include:
            NEEDS FORM
            ORDER HOLD WAITING FOR RXS
            TRANSFER OUT OF ALL ITEMS
            ACTION PATIENT OFF AUTOFILL
    */

    // count_items in addition to count_filled because it might be a manually
    //  added item, that we are not filling but that the pharmacist is using
    //  as a placeholder/reminder e.g 54732
    if ($order[0]['count_items'] == 0
        && $order[0]['count_filled'] == 0
        && $order[0]['count_to_add'] == 0
        && !is_webform_transfer($order[0])) {
        AuditLog::log(
            sprintf(
                "Order %s has no Rx to fill so it will be cancelled",
                $updated['invoice_number']
            ),
            $updated
        );
        GPLog::error(
            'update_orders_cp: updated. no drugs to fill. removing cp/wc order '.$order[0]['invoice_number'].'. Send order cancelled notice?',
            [
                'invoice_number' => $order[0]['invoice_number'],
                'count_filled'   => $order[0]['count_filled'],
                'count_items'    => $order[0]['count_items'],
                'order'          => $order
            ]
        );

        /*
            TODO Why do we need to explicitly unpend?  Deleting an order in
            CP should trigger the deleted loop on next run, which should unpend
            But it seemed that this didn't happen for Order 53684

            TODO Necessary to Remove/Cancel WC Order Here?
         */
        $order = export_v2_unpend_order($order, $mysql, 'Updated Empty');
        export_cp_remove_order($order[0]['invoice_number'], 'Updated Empty');

        if (is_webform($order[0])) {
            export_wc_cancel_order($order[0]['invoice_number'], "Order Empty");
        } else {
            export_wc_delete_order($order[0]['invoice_number'], "Order Empty");
        }

        return null;
    }

    //Address Changes
    //Stage Change
    //Order_Source Change (now that we overwrite when saving webform)
    GPLog::notice(
        "update_orders_cp updated: no action taken {$updated['invoice_number']}",
        [
            'order'   => $order,
            'updated' => $updated,
            'changed' => $changed
        ]
    );

    GPLog::resetSubroutineId();
    return $updated;
}
