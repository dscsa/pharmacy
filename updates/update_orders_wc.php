<?php

require_once 'helpers/helper_full_order.php';

use Sirum\Logging\SirumLog;
use Sirum\Logging\AuditLog;

function update_orders_wc($changes)
{
    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
    echo $msg;
    SirumLog::info(
        "update_orders_wc: all changes. {$msg}",
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

    //This captures 2 USE CASES:
    //1) A user/tech created an order in WC and we need to add it to Guardian
    //2) An order is incorrectly saved in WC even though it should be gone (tech bug)


    /*
        Since CP Order runs before this AND Webform automatically adds Orders
        into CP this loop should not have actual created orders.  They are all
        orders that were deleted in CP and were overlooked by the cp_order
        delete loop
     */
    $loop_timer = microtime(true);

    foreach ($changes['created'] as $created) {
        SirumLog::$subroutine_id = "orders-wc-created-".sha1(serialize($created));

        SirumLog::debug(
            "update_orders_wc: WooCommerce Order Created",
            [
                'source'  => 'WooCommerce',
                'event'   => 'created',
                'type'    => 'orders',
                'created' => $created
            ]
        );

        $duplicate = get_current_orders($mysql, ['patient_id_wc' => $created['patient_id_wc']]);

        if ($duplicate) {
            AuditLog::log(
                sprintf(
                    "Order #%s was created in Patient Portal,
                    but it appears #%s is a replacement",
                    $created['invoice_number'],
                    $duplicate['invoice_number']
                ),
                $created
            );

            SirumLog::warning(
                'order_canceled_notice BUT their appears to be a replacement',
                [
                    'created' => $created,
                    'duplicate' => $duplicate
                ]
            );

            // In case there is an order_note move it to the first order so we
            // don't lose it, when we delete this order
            if ($created['order_note']) {
                export_cp_append_order_note($mssql, $duplicate[0]['invoice_number'], $created['order_note']);
            }
        }
        /*
            Possible values for order_stage_wc include:
                'Webform Complete'
                'Webform eRx'
                'Webform Transfer'
                'Auto Refill'
                '0 Refills'
                'Webform Refill'
                'eRx /w Note'
                'Transfer /w Note'
                'Refill w/ Note'
         */
        if (stripos($created['order_stage_wc'], 'confirm') !== false
            or stripos($created['order_stage_wc'], 'trash') !== false) {
            AuditLog::log(
                sprintf(
                    "Order #%s was created in Patient Portal, but should be coming
                    from CarePoint since the new order has a status of %s it
                    will be DELETED",
                    $created['invoice_number'],
                    $created['order_stage_wc']
                ),
                $created
            );
            export_wc_delete_order(
                $created['invoice_number'],
                sprintf(
                    "update_orders_cp: cp order deleted %s %s %s %s",
                    $created['invoice_number'],
                    $created['order_stage_wc'],
                    $created['order_source'],
                    json_encode($created)
                )
            );
            export_gd_delete_invoice($created['invoice_number']);
            continue;
        }
        /*
            Possible values for order_stage_wc include:
                'Webform Complete'
                'Webform eRx'
                'Webform Transfer'
                'Auto Refill'
                '0 Refills'
                'Webform Refill'
                'eRx /w Note'
                'Transfer /w Note'
                'Refill w/ Note'
         */
        if (stripos($created['order_stage_wc'], 'prepare') !== false) {
            AuditLog::log(
                sprintf(
                    "Order #%s was created in Patient Portal, but should be coming
                    from CarePoint since the new order has a status of %s it
                    will be CANCELLED",
                    $created['invoice_number'],
                    $duplicate['invoice_number'],
                    $created['order_stage_wc']
                ),
                $created
            );
            export_wc_cancel_order(
                $created['invoice_number'],
                sprintf(
                    "update_orders_cp: cp order canceled %s %s %s %s",
                    $created['invoice_number'],
                    $created['order_stage_wc'],
                    $created['order_source'],
                    json_encode($created)
                )
            );
            continue;
        }

        SirumLog::alert(
            "update_orders_wc: WooCommerce Order Created. Needs Manual Intervention!",
            [
                'invoice_number' => $created['invoice_number'],
                'order_stage_wc' => $created['order_stage_wc'],
                'source'         => 'WooCommerce',
                'event'          => 'created',
                'type'           => 'orders',
                'created'        => $created
            ]
        );
    }
    log_timer('orders-wc-created', $loop_timer, $count_created);


    /*
        This captures 2 USE CASES:
            1) An order is in WC and CP but then is deleted in WC, probably
            because wp-admin deleted it (look for Update with
            order_stage_wc == 'trash')
            2) An order is in CP but not in (never added to) WC, probably
            because of a tech bug.
     */
    $loop_timer = microtime(true);

    foreach ($changes['deleted'] as $deleted) {
        SirumLog::$subroutine_id = "orders-wc-deleted-".sha1(serialize($deleted));

        SirumLog::debug(
            "update_orders_wc: WooCommerce Order Deleted",
            [
                'source'  => 'WooCommerce',
                'event'   => 'deleted',
                'type'    => 'orders',
                'deleted' => $deleted
            ]
        );

        /*
          For non-webform orders, on the first run of orders-cp-created wc-order
          will not have yet been created so WC wasn't "deleted" it just wasn't
          created yet.  But once order_stage_wc is set, then it is a true deletion
         */
        if (is_null($deleted['order_stage_wc']) AND ! $deleted['order_date_dispensed']) {
            //continue;
        }

        $order = load_full_order($deleted, $mysql);

        AuditLog::log(
            sprintf(
                "Order #%s has been deleted from the Patient Portal.",
                $deleted['invoice_number']
            ),
            $deleted
        );

        SirumLog::alert(
            "Order deleted from WC. Why?",
            [
                'source'  => 'WooCommerce',
                'event'   => 'deleted',
                'type'    => 'orders',
                'deleted' => $deleted,
                'order'   => $order
            ]
        );

        $order = helper_update_payment($order, "update_orders_wc: shipped order deleted from WC", $mysql);
        export_wc_create_order($order, "update_orders_wc: shipped order deleted from WC");

        if ($deleted['order_date_shipped'] or $deleted['order_date_returned']) {

            AuditLog::log(
                sprintf(
                    "Order #%s has was shipped before being deleted",
                    $deleted['invoice_number']
                ),
                $deleted
            );

            SirumLog::alert(
                "Shipped Order deleted from WC. Republishing Invoice",
                [
                    'source'  => 'WooCommerce',
                    'event'   => 'deleted',
                    'type'    => 'orders',
                    'deleted' => $deleted,
                    'order'   => $order
                ]
            );

            $order = export_gd_publish_invoice($order, $mysql);
        }
    }

    log_timer('orders-wc-deleted', $loop_timer, $count_deleted);

    $loop_timer = microtime(true);

    foreach ($changes['updated'] as $updated) {
        SirumLog::$subroutine_id = "orders-wc-updated-".sha1(serialize($updated));

        SirumLog::debug(
            "update_orders_wc: WooCommerce Order Updated",
            [
                'source'  => 'WooCommerce',
                'event'   => 'updated',
                'type'    => 'orders',
                'updated' => $updated
            ]
        );

        $changed = changed_fields($updated);

        $new_stage = explode('-', $updated['order_stage_wc']);
        $old_stage = explode('-', $updated['old_order_stage_wc']);

        AuditLog::log(
            sprintf(
                "Order #%s has been updated in the Patient Portal.
                The status has been set to %s",
                $updated['invoice_number'],
                $updated['order_stage_wc']
            ),
            $updated
        );

        if ($updated['order_stage_wc'] != $updated['old_order_stage_wc'] and
          ! (
                (empty($old_stage[1])        and $new_stage[1] == 'confirm') or
                (empty($old_stage[1])        and $new_stage[1] == 'prepare') or
                (empty($old_stage[1])        and $new_stage[1] == 'shipped') or
                (empty($old_stage[1])        and $new_stage[1] == 'late') or
                (@$old_stage[1] == 'confirm' and $new_stage[1] == 'prepare') or
                (@$old_stage[1] == 'confirm' and $new_stage[1] == 'shipped') or
                (@$old_stage[1] == 'confirm' and $new_stage[1] == 'late') or
                (@$old_stage[1] == 'prepare' and $new_stage[1] == 'prepare') or //User completes webform twice then prepare-refill will overwrite prepare-erx
                (@$old_stage[1] == 'prepare' and $new_stage[1] == 'shipped') or
                (@$old_stage[1] == 'prepare' and $new_stage[1] == 'late') or
                (@$old_stage[1] == 'prepare' and $new_stage[1] == 'done') or
                (@$old_stage[1] == 'shipped' and $new_stage[1] == 'done') or
                (@$old_stage[1] == 'late'    and $new_stage[1] == 'done') or
                (@$old_stage[1] == 'shipped' and $new_stage[1] == 'late') or
                (@$old_stage[1] == 'shipped' and $new_stage[1] == 'returned') or
                (@$old_stage[1] == 'shipped' and $new_stage[1] == 'shipped')
          )
        ) {
            SirumLog::error(
                "WC Order Irregular Stage Change.",
                [
                    "invoice_number"  => $updated['invoice_number'],
                    "old_stage"       => $updated['old_order_stage_wc'],
                    "new_stage"       => $updated['order_stage_wc'],
                    'old_stage_array' => $old_stage,
                    'new_stage_array' => $new_stage,
                    "changed"         => $changed,
                    "method"          => "update_orders_wc"
                ]
            );
        }
    } // End Changes Loop

    log_timer('orders-wc-updated', $loop_timer, $count_updated);

    SirumLog::resetSubroutineId();
}
