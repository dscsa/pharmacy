<?php

require_once 'changes/changes_to_orders_wc.php';
require_once 'helpers/helper_full_order.php';

use Sirum\Logging\SirumLog;

function update_orders_wc()
{
    $changes = changes_to_orders_wc("gp_orders_wc");

    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    if (! $count_deleted and ! $count_created and ! $count_updated) {
        SirumLog::notice(
            'No changes found, leaving update_orders_wc',
            [
              'deleted' => $changes['deleted'],
              'created' => $changes['created'],
              'updated' => $changes['updated'],
              'deleted_count' => $count_deleted,
              'created_count' => $count_created,
              'updated_count' => $count_updated
            ]
        );
        return;
    }

    SirumLog::debug(
        'WooCommerce Changes found',
        [
          'deleted' => $changes['deleted'],
          'created' => $changes['created'],
          'updated' => $changes['updated'],
          'deleted_count' => $count_deleted,
          'created_count' => $count_created,
          'updated_count' => $count_updated
        ]
    );

    $mysql = new Mysql_Wc();

    //This captures 2 USE CASES:
    //1) A user/tech created an order in WC and we need to add it to Guardian
    //2) An order is incorrectly saved in WC even though it should be gone (tech bug)
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
        $new_stage = explode('-', $created['order_stage_wc']);

        if ($created['order_stage_wc'] == 'trash' or $new_stage[1] == 'awaiting' or $new_stage[1] == 'confirm') {
            log_info("Empty Orders are intentially not imported into Guardian", "$created[invoice_number] $created[order_stage_wc]");
        } else if (in_array(
            $created['order_stage_wc'],
            [
              'wc-shipped-unpaid',
              'wc-shipped-paid',
              'wc-shipped-paid-card',
              'wc-shipped-paid-mail',
              'wc-shipped-refused',
              'wc-done-card-pay',
              'wc-done-mail-pay',
              'wc-done-coupon-pay',
              'wc-done-auto-pay'
             ]
        )) {
            log_error("Shipped/Paid WC not in Guardian. Delete/Refund?", $created);

        //This comes from import_wc_orders so we don't need the "w/ Note" counterpart sources
        } else if (in_array($created['order_source'], ["Webform Refill", "Webform Transfer", "Webform eRx"])) {
            //TODO Investigate #29187

            $gp_orders_pend = $mysql->run("SELECT * FROM gp_orders WHERE patient_id_wc = $created[patient_id_wc] AND (order_stage_wc LIKE '%prepare%' OR order_stage_wc LIKE '%confirm%')");
            $gp_orders_all = $mysql->run("SELECT * FROM gp_orders WHERE patient_id_wc = $created[patient_id_wc]");

            if ($gp_orders_pend[0]) {
                export_gd_delete_invoice([$created], $mysql);
                export_wc_delete_order($created['invoice_number'], "update_orders_wc: wc order 'created' but probably just not deleted when CP order was ".json_encode($created));
                log_error("update_orders_wc: Deleting Webform eRx/Refill/Transfer order that was not in CP.  Most likely patient submitted two orders (e.g. 32121 & 32083 OR 32783 & 32709).  Why was this not deleted when CP Order was deleted?", ['gp_orders_pend' => $gp_orders_pend, 'gp_orders_all' => $gp_orders_all, 'created' => $created]);//.print_r($item, true);
            } else {
                log_error("update_orders_wc: created Webform eRx/Refill/Transfer order that is not in CP? Unknown reason", ['gp_orders_pend' => $gp_orders_pend, 'gp_orders_all' => $gp_orders_all, 'created' => $created]);//.print_r($item, true);
            }


            //log_notice("New WC Order to Add Guadian", $created);
        } else {
            //TODO Investigate #29147

            $gp_orders     = $mysql->run("SELECT * FROM gp_orders WHERE invoice_number = $created[invoice_number]");
            $gp_orders_cp  = $mysql->run("SELECT * FROM gp_orders_cp WHERE invoice_number = $created[invoice_number]");
            $gp_orders_all = $mysql->run("SELECT * FROM gp_orders WHERE patient_id_wc = $created[patient_id_wc]");

            log_error("update_orders_wc: created non-Webform order that is not in CP? 1) One-Time: deleted by helper_syncing and will be readded, 2: Repeated: deleted by Pharmacist in CP and should be investigated (33287 maybe sig_qty_per_day grouping error)", ['gp_orders_all' => $gp_orders_all, 'gp_orders_cp' => $gp_orders_cp, 'gp_orders' => $gp_orders, 'created' => $created]);//.print_r($item, true);

      //log_notice("Guardian Order Deleted that should be deleted from WC later in this run or already deleted", $created);
        }
    }

    //This captures 2 USE CASES:
    //1) An order is in WC and CP but then is deleted in WC, probably because wp-admin deleted it (look for Update with order_stage_wc == 'trash')
    //2) An order is in CP but not in (never added to) WC, probably because of a tech bug.
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

        $order = get_full_order($deleted, $mysql);

        /* TODO Investigate if/why this is needed */
        if (! $order) {
            log_error("update_orders_wc: deleted WC order that is not in CP?", $deleted);
        } elseif ($deleted['order_stage_wc'] == 'trash') {
            if ($deleted['tracking_number']) {
                log_notice("Shipped Order deleted from trash in WC. Why?", $deleted);

                $order = helper_update_payment($order, "update_orders_wc: deleted - trash", $mysql);

                export_wc_create_order($order, "update_orders_wc: deleted - trash");

                export_gd_publish_invoice($order, $mysql);
            }
        } elseif (! $order[0]['pharmacy_name']) {  //Can't do $order[0]['rx_message_key'] == 'ACTION NEEDS FORM' because other keys can take precedence even if form is needed
            //TODO eventually set registration comm-calendar event then delete order but right now just remove items from order
            //If all items are removed, order will not be imported from CP
            $items_to_remove = [];
            foreach ($order as $item) {
                if ($item['item_date_added'] and $item['item_added_by'] != 'MANUAL' and ! $item['rx_dispensed_id']) {
                    $items_to_remove[] = $item['rx_number'];
                }
            }

            SirumLog::notice(
              "update_orders_wc deleted: export_cp_remove_items",
              [
                'invoice_number' => $order[0]['invoice_number'],
                'reason' => 'update_orders_wc: RXs created an order in CP but patient has not yet registered so there is no order in WC yet',
                'items_to_remove' => $items_to_remove,
                'order' => $order
              ]
            );

            export_cp_remove_items($order[0]['invoice_number'], $items_to_remove);

        } elseif ($deleted['order_stage_cp'] == 'Shipped' or $deleted['order_stage_cp'] == 'Dispensed') {
            $gp_orders_wc = $mysql->run("SELECT * FROM gp_orders_wc WHERE invoice_number = $deleted[invoice_number]")[0];
            $gp_orders = $mysql->run("SELECT * FROM gp_orders WHERE invoice_number = $deleted[invoice_number]")[0];
            $wc_orders = wc_get_post_id($deleted['invoice_number']);

            $order = helper_update_payment($order, "update_orders_wc: deleted - unknown reason", $mysql);

            export_wc_create_order($order, "update_orders_wc: deleted - unknown reason");

            export_gd_publish_invoice($order, $mysql);

            log_error("Readding Order that should not have been deleted. Not sure: WC Order Deleted not through trash?", [$order[0], $gp_orders_wc, $gp_orders, $wc_orders]);
        } elseif ((time() - strtotime($deleted['order_date_added'])) < 1*60*60) {
            //If less than an hour, this is likely because of our IMPORT ordering since we import WC Orders before CP some orders can show up in this deleted feed.
            log_error("update_orders_wc: WC Order $deleted[invoice_number] appears to be DELETED but likely because or the IMPORT ordering", $deleted);
        } else {
            $wc_orders = wc_get_post_id($deleted['invoice_number'], true);

            $sql = get_deleted_sql("gp_orders_wc", "gp_orders", "invoice_number");

            $gp_orders      = $mysql->run("SELECT * FROM gp_orders WHERE invoice_number = $deleted[invoice_number]");
            $gp_orders_wc   = $mysql->run("SELECT * FROM gp_orders_wc WHERE invoice_number = $deleted[invoice_number]");
            $gp_orders_cp   = $mysql->run("SELECT * FROM gp_orders_cp WHERE invoice_number = $deleted[invoice_number]");
            //$deleted_orders = $mysql->run("SELECT old.* FROM gp_orders_wc as new RIGHT JOIN gp_orders as old ON old.invoice_number = new.invoice_number WHERE new.invoice_number IS NULL");

            //TODO WHAT IS GOING ON HERE?
            //Idea1:  Order had all items removed so it appeared to be deleted from CP, but when items were added back in the order 'reappeared'
            //Idea2: Failed when trying to be added to WC (e.g., in #28162 the patient could not be found)
            //Neither Idea1 or Idea2 seems to be the case for Order 29033
            SirumLog::error(
                "WC Order Appears to be DELETED. RECREATING! (If repeated possible Patient Name Mismatch? Or Processing Invoice # needs to be Updated?",
                [
                  'invoice_number' => $deleted['invoice_number'],
                  'order[0]'       => $order[0],
                  'deleted'        => $deleted,
                  'wc_post_id'     => $wc_orders,
                  'gp_orders'      => $gp_orders,
                  'gp_orders_wc'   => $gp_orders_wc,
                  'gp_orders_cp'   => $gp_orders_cp,
                  'sql'            => $sql
                ]
            );

            $order = helper_update_payment($order, "update_orders_wc: deleted - not shipped but still recreating", $mysql);

            export_wc_create_order($order, "update_orders_wc: deleted - not shipped but still recreating");

            export_gd_publish_invoice($order, $mysql);
        }
    }

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

        if ($old_stage[0] == 'trash' and $updated['patient_id_wc'] == $updated['old_patient_id_wc']) {
            SirumLog::debug(
                "WC Order was removed from trash OR a duplicate order exists and the trashed order needs to be permanently deleted",
                [
                  "invoice_number" => $updated['invoice_number'],
                  "updated"        => $updated,
                  "method"         => "update_orders_wc"
                ]
            );
        } elseif ($old_stage[0] == 'trash' and $updated['patient_id_wc'] != $updated['old_patient_id_wc']) {

      //log_error('WC Order was recreated with correct patient id', $updated);
        } elseif ($new_stage[0] == 'trash') {
            if ($old_stage[1] == 'shipped' or $old_stage[1] == 'done' or $old_stage[1] == 'late' or $old_stage[1] == 'return') {
                SirumLog::notice(
                    "Shipped Order trashed in WC. Are you sure you wanted to do this?",
                    [
                        "invoice_number" => $updated['invoice_number'],
                        "updated"        => $updated,
                        "method"         => "update_orders_wc"
                    ]
                );
            } else {
                SirumLog::debug(
                    "get_full_order: WooCommerce Updated",
                    ['updated' => $updated]
                );

                $order = get_full_order($updated, $mysql);

                if (! $order) {
                    SirumLog::notice(
                        "Non-Shipped Order trashed in WC",
                        [
                            "invoice_number" => $updated['invoice_number'],
                            "updated"        => $updated,
                            "method"         => "update_orders_wc"
                          ]
                    );

                    continue;
                }

                SirumLog::error(
                    "Guardian order marked trashed.",
                    [
                      "old_stage"      => $order[0]['order_stage_wc'],
                      "invoice_number" => $order[0]['invoice_number'],
                      "order"          => $order,
                      "method"         => "update_orders_wc"
                    ]
                );


                export_wc_update_order_status($order); //Update to current status
                export_wc_update_order_metadata($order);
            }
        } elseif ($updated['order_stage_wc'] and ! $updated['old_order_stage_wc'] and $updated['old_patient_id_wc']) {
            //Admin probably set order_stage_wc to NULL directly in database, hoping to refresh the order
            SirumLog::debug(
                "get_full_order: WooCommerce updated",
                ['updated' => $updated]
            );

            $order = get_full_order($updated, $mysql);

            SirumLog::notice(
                "WC Order Updating from NULL Status",
                [
                    "old_stage"      => $old_stag,
                    "new_stage"      => $new_stage,
                    "invoice_number" => $updated['invoice_number'],
                    "order"          => $order,
                    "updated"        => $updated,
                    "method"         => "update_orders_wc"
                ]
            );


            export_wc_update_order_status($order); //Update to current status
            export_wc_update_order_metadata($order);
        } elseif (count($changed) == 1 and $updated['order_stage_wc'] != $updated['old_order_stage_wc']) {
            if (
                ($old_stage[1] == 'confirm' and $new_stage[1] == 'prepare') or
                ($old_stage[1] == 'confirm' and $new_stage[1] == 'shipped') or
                ($old_stage[1] == 'confirm' and $new_stage[1] == 'late') or
                ($old_stage[1] == 'prepare' and $new_stage[1] == 'prepare') or //User completes webform twice then prepare-refill will overwrite prepare-erx
                ($old_stage[1] == 'prepare' and $new_stage[1] == 'shipped') or
                ($old_stage[1] == 'prepare' and $new_stage[1] == 'late') or
                ($old_stage[1] == 'prepare' and $new_stage[1] == 'done') or
                ($old_stage[1] == 'shipped' and $new_stage[1] == 'done') or
                ($old_stage[1] == 'late'    and $new_stage[1] == 'done') or
                ($old_stage[1] == 'shipped' and $new_stage[1] == 'late') or
                ($old_stage[1] == 'shipped' and $new_stage[1] == 'returned') or
                ($old_stage[1] == 'shipped' and $new_stage[1] == 'shipped') //TODO Enable change of preferred payment method
              ) {
                SirumLog::debug(
                    "WC Order Normal Stage Change",
                    [
                      "invoice_number" => $updated['invoice_number'],
                      "changed"        => $changed,
                      "method"         => "update_orders_wc"
                    ]
                );
            } else {
                SirumLog::debug(
                    "get_full_order: WooCommerce Updated",
                    ['updated' => $updated]
                );

                $order = get_full_order($updated, $mysql);
                SirumLog::error(
                    "WC Order Irregular Stage Change.",
                    [
                      "old_stage"      => $updated['old_order_stage_wc'],
                      "new_stage"      => $updated['order_stage_wc'],
                      "invoice_number" => $updated[invoice_number],
                      "changed"        => $changed,
                      "method"         => "update_orders_wc"
                    ]
                );

                export_wc_update_order_status($order); //Update to current status
                export_wc_update_order_metadata($order);
            }
        } elseif (! $updated['patient_id_wc'] and $updated['old_patient_id_wc']) {

      //26214, 26509
            SirumLog::error(
                "WC Patient Id Removed from Order.  Likely a patient was deleted from WC that still had an order",
                [
                    "invoice_number" => $updated['invoice_number'],
                    "changed"        => $changed,
                    "updated"        => $updated,
                    "method"         => "update_orders_wc"
                  ]
            );
        } elseif ($updated['patient_id_wc'] and ! $updated['old_patient_id_wc']) {
            //26214, 26509
            SirumLog::debug(
                "WC Order was created on last run and now patient_id_wc can be added",
                [
                    "invoice_number" => $updated['invoice_number'],
                    "changed"        => $changed,
                    "method"         => "update_orders_wc"
                  ]
            );
        } else {
            SirumLog::debug(
                "WC Order was created on last run and now patient_id_wc can be added",
                [
                    "invoice_number" => $updated['invoice_number'],
                    "changed"        => $changed,
                    "updated"        => $updated,
                    "method"         => "update_orders_wc"
                  ]
            );
        }
    } // End Changes Loop
    SirumLog::resetSubroutineId();
}
