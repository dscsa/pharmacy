<?php

require_once 'helpers/helper_full_order.php';

use Sirum\Logging\SirumLog;

function update_orders_wc($changes) {

    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
    echo $msg;
    log_info("update_orders_wc: all changes. $msg", [
      'deleted_count' => $count_deleted,
      'created_count' => $count_created,
      'updated_count' => $count_updated
    ]);

    if ( ! $count_deleted and ! $count_created and ! $count_updated) return;

    $mysql = new Mysql_Wc();

    //This captures 2 USE CASES:
    //1) A user/tech created an order in WC and we need to add it to Guardian
    //2) An order is incorrectly saved in WC even though it should be gone (tech bug)


    //Since CP Order runs before this AND Webform automatically adds Orders into CP
    //this loop should not have actual created orders.  They are all orders that were
    //deleted in CP and were overlooked by the cp_order delete loop
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

        $replacement = get_current_orders($mysql, ['patient_id_wc' => $created['patient_id_wc']]);

        if ($replacement) {
          log_warning('order_canceled_notice BUT their appears to be a replacement', ['created' => $created, 'replacement' => $replacement]);
        }

        //[NULL, 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
        if (stripos($created['order_stage_wc'], 'confirm') !== false OR stripos($created['order_stage_wc'], 'trash') !== false) {
          export_wc_delete_order($created['invoice_number'], "update_orders_cp: cp order deleted $created[invoice_number] $created[order_stage_wc] $created[order_source] ".json_encode($created));
          continue;
        }

        //[NULL, 'Webform Complete', 'Webform eRx', 'Webform Transfer', 'Auto Refill', '0 Refills', 'Webform Refill', 'eRx /w Note', 'Transfer /w Note', 'Refill w/ Note']
        if (stripos($created['order_stage_wc'], 'prepare')) {
          export_wc_cancel_order($created['invoice_number'], "update_orders_cp: cp order canceled $created[invoice_number] $created[order_stage_wc] $created[order_source] ".json_encode($created));
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

        //export_wc_cancel_order($created['invoice_number'], "update_orders_cp: cp order canceled $created[invoice_number] $created[order_stage_cp] $created[order_stage_wc] $created[order_source] ".json_encode($created));
    }
    log_timer('orders-wc-created', $loop_timer, $count_created);


    //This captures 2 USE CASES:
    //1) An order is in WC and CP but then is deleted in WC, probably because wp-admin deleted it (look for Update with order_stage_wc == 'trash')
    //2) An order is in CP but not in (never added to) WC, probably because of a tech bug.
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

      //For non-webform orders, on the first run of orders-cp-created wc-order will not have yet been created
      //so WC wasn't "deleted" it just wasn't created yet.  But once order_stage_wc is set, then it is a true deletion
      if (is_null($deleted['order_stage_wc'])) {
        print_r($deleted);
        //continue;
      }

      $order = load_full_order($deleted, $mysql);

      log_alert("Order deleted from WC. Why?", [
        'source'  => 'WooCommerce',
        'event'   => 'deleted',
        'type'    => 'orders',
        'deleted' => $deleted,
        'order'   => $order
      ]);

      export_wc_create_order($order, "update_orders_wc: shipped order deleted from WC");

      if ($deleted['tracking_number'] OR $deleted['order_stage_cp'] == 'Shipped' OR $deleted['order_stage_cp'] == 'Dispensed') {

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
