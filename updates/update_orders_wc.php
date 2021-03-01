<?php

require_once 'helpers/helper_full_order.php';
require_once 'helpers/helper_try_catch_log.php';

use GoodPill\Logging\GPLog;
use GoodPill\Logging\AuditLog;
use GoodPill\Logging\CliLog;
use GoodPill\Utilities\Timer;

/**
 * Proccess all the updates to WooCommerce Orders
 * @param  array $changes  An array of arrays with deledted, created, and
 *      updated elements
 * @return void
 */
function update_orders_wc(array $changes) : void
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
        "update_orders_wc: changes",
        $change_counts
    );

    GPLog::notice('data-update-orders-wc', $changes);

    if (isset($changes['created'])) {
        Timer::start("update.orders.wc.created");
        //This captures 2 USE CASES:
        //1) A user/tech created an order in WC and we need to add it to Guardian
        //2) An order is incorrectly saved in WC even though it should be gone (tech bug)

        /*
            Since CP Order runs before this AND Webform automatically adds Orders
            into CP this loop should not have actual created orders.  They are all
            orders that were deleted in CP and were overlooked by the cp_order
            delete loop
         */
        foreach ($changes['created'] as $created) {
            helper_try_catch_log('wc_order_created', $created);
        }
        Timer::stop("update.orders.wc.created");
    }

    /*
        This captures 2 USE CASES:
            1) An order is in WC and CP but then is deleted in WC, probably
            because wp-admin deleted it (look for Update with
            order_stage_wc == 'trash')
            2) An order is in CP but not in (never added to) WC, probably
            because of a tech bug.
     */
    if (isset($changes['deleted'])) {
        Timer::start("update.orders.wc.deleted");
        foreach ($changes['deleted'] as $deleted) {
            helper_try_catch_log('wc_order_deleted', $deleted);
        }
        Timer::stop("update.orders.wc.deleted");
    }

    if (isset($changes['updated'])) {
        Timer::start("update.orders.wc.updated");
        foreach ($changes['updated'] as $updated) {
            helper_try_catch_log('wc_order_updated', $updated);
        } // End Changes Loop
        Timer::stop("update.orders.wc.updated");
    }
}

/*

    Change Hanlders

 */


/**
 * Handle the Created WooCommerce Orders
 * @param  array $updated The data for the create
 * @return bool           True if it made it to the bottom of the function
 */
function wc_order_created(array $created) : bool
{
    GPLog::$subroutine_id = "orders-wc-created-".sha1(serialize($created));
    GPLog::info("data-orders-wc-created", ['created' => $created]);
    GPLog::debug(
        "update_orders_wc: WooCommerce Order Created",
        [
            'source'  => 'WooCommerce',
            'event'   => 'created',
            'type'    => 'orders',
            'created' => $created
        ]
    );

    $mysql = new Mysql_Wc();

    $duplicate = get_current_orders($mysql, ['patient_id_wc' => $created['patient_id_wc']]);

    if ($duplicate) {
        AuditLog::log(
            sprintf(
                "Order #%s was created in Patient Portal,
                but it appears to be a duplicate of #%s",
                $created['invoice_number'],
                $duplicate[0]['invoice_number']
            ),
            $created
        );

        GPLog::warning(
            sprintf(
                "Order #%s was created in Patient Portal,
                but it appears to be a duplicate of #%s",
                $created['invoice_number'],
                $duplicate[0]['invoice_number']
            ),
            [
                'created' => $created,
                'duplicate' => $duplicate
            ]
        );

        // In case there is an order_note move it to the first order so we
        // don't lose it, when we delete this order
        if ($created['order_note']) {
            $mssql = $mssql ?: new Mssql_Cp();
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
        return false;
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
                @$created['invoice_number'],
                @$duplicate['invoice_number'],
                $created['order_stage_wc']
            ),
            $created
        );

        export_wc_cancel_order(
            $created['invoice_number'],
            sprintf(
                "update_orders_cp: cp order cancelled %s %s %s %s",
                @$created['invoice_number'],
                $created['order_stage_wc'],
                $created['order_source'],
                json_encode($created)
            )
        );
        return false;
    }

    GPLog::critical(
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

    GPLog::resetSubroutineId();
    return true;
}


/**
 * Handle the Deleted WooCommerce Orders
 * @param  array $deleted The data for the delete
 * @return bool           True if it made it to the bottom of the function
 */
function wc_order_deleted(array $deleted) : bool
{
    GPLog::$subroutine_id = "orders-wc-deleted-".sha1(serialize($deleted));
    GPLog::info("data-orders-wc-deleted", ['deleted' => $deleted]);
    GPLog::debug(
        "update_orders_wc: WooCommerce Order Deleted",
        [
            'source'  => 'WooCommerce',
            'event'   => 'deleted',
            'type'    => 'orders',
            'deleted' => $deleted
        ]
    );

    $mysql = new Mysql_Wc();

    /*
      For non-webform orders, on the first run of orders-cp-created wc-order
      will not have yet been created so WC wasn't "deleted" it just wasn't
      created yet.  But once order_stage_wc is set, then it is a true deletion
     */
    if (is_null($deleted['order_stage_wc']) and ! $deleted['order_date_dispensed']) {
        return false;
    }

    $order = load_full_order($deleted, $mysql);

    AuditLog::log(
        sprintf(
            "Order #%s has been deleted from the Patient Portal.",
            $deleted['invoice_number']
        ),
        $deleted
    );

    GPLog::critical(
        "Order deleted from WC. Why?",
        [
            'source'  => 'WooCommerce',
            'event'   => 'deleted',
            'type'    => 'orders',
            'deleted' => $deleted,
            'order'   => $order
        ]
    );

    //NOTE the below will fail if the order is wc-cancelled.  because it will show up as deleted here
    //but if it was improperly cancelled and the cp order still exists then export_wc_create_order()
    //will fail because it technically exists it just isn't being imported (deliberitely)

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

        GPLog::critical(
            "Shipped Order deleted from WC. Republishing Invoice",
            [
                'source'  => 'WooCommerce',
                'event'   => 'deleted',
                'type'    => 'orders',
                'deleted' => $deleted,
                'order'   => $order
            ]
        );

        $order = export_gd_publish_invoice($order);
    }

    GPLog::resetSubroutineId();
    return true;
}

/**
 * Handle the Updated WooCommerce Orders
 * @param  array $updated The data for the update
 * @return bool           True if it made it to the bottom of the function
 */
function wc_order_updated(array $updated) : bool
{
    GPLog::$subroutine_id = "orders-wc-updated-".sha1(serialize($updated));
    GPLog::info("data-orders-wc-updated", ['updated' => $updated]);
    GPLog::debug(
        "update_orders_wc: WooCommerce Order Updated",
        [
            'source'  => 'WooCommerce',
            'event'   => 'updated',
            'type'    => 'orders',
            'updated' => $updated
        ]
    );

    $mysql = new Mysql_Wc();

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
        GPLog::error(
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
    GPLog::resetSubroutineId();
    return true;
}
