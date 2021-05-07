<?php

global $mssql;

require_once 'exports/export_cp_order_items.php';

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};


function export_cp_set_expected_by($item) {

  global $mssql;
  $cp_automation_user = CAREPOINT_AUTOMATION_USER;

    // this should happen if it does, throw an error and return.
  if (empty($item['invoice_number'])) {
      GPLog::critical(
          'Trying to update CP Expected date, but missing invoice_nbr',
          [ 'item' => $item ]
      );
      return;
  }

  $mssql           = $mssql ?: new Mssql_Cp();
  $pend_group_name = pend_group_name($item);

  //New Patient Date (designated with a "P") is based on patient_date_added,
  //which is the lesser of Rxs received and registration date, because both of these
  //events would create the patient if it did not already exist.  This date is good
  //for v2 shopping priority, but it's unfair to be the expected_by date for the Rphs
  //so for the expected_by date we will replace it with the greater of Rxs received and registration
  if (strpos($pend_group_name, 'P') !== false) {

        $expected_by = $item['order_date_added'];

  } else {
        /*
        Last part of order_date_added isn't "necessary" because CP doesn't display full
        timestamp in "date order added" field /in the F9 queue.  If you know about
        this trick, you can find the timestamp in Guardian's expected by date.
        */
        $expected_by  = substr($pend_group_name, 0, 10);
        $expected_by .= substr($item['order_date_added'], -9); //timestamp with leading space
  }

  $sql = "UPDATE csom
          SET expected_by = '{$expected_by}',
              chg_user_id = '{$cp_automation_user}'
          WHERE invoice_nbr = {$item['invoice_number']}";

  GPLog::notice(
    "export_cp_set_expected_by: pend group name $pend_group_name $item[invoice_number]",
    [
      'expected_by'     => $expected_by,
      'pend_group_name' => $pend_group_name,
      'invoice_number'  => $item['invoice_number'],
      'sql'             => $sql
    ]
  );

  $res = $mssql->run($sql);
}

function export_cp_remove_order($invoice_number, $reason) {

  global $mssql;
  $mssql = $mssql ?: new Mssql_Cp();
  $cp_automation_user = CAREPOINT_AUTOMATION_USER;

    GPLog::notice(
    "export_cp_remove_order: Order deleting $invoice_number",
    [
      'invoice_number'  => $invoice_number,
      'reason'          => $reason
    ]
  );

  $new_count_items = export_cp_remove_items($invoice_number); //since no 2nd argument this removes all undispensed items

  if ( ! $new_count_items) { //if no items were dispensed yet, archive order

    $sql = "
      UPDATE csom 
      SET status_cn = 3,
          chg_user_id = '{$cp_automation_user}'
      WHERE invoice_nbr = $invoice_number -- chg_user_id = @user_id, chg_date = @today
    ";

    $res = $mssql->run($sql);

    $date = date('y-m-d H:i');
    export_cp_append_order_note($mssql, $invoice_number, "Auto Deleted $date. $reason");

    GPLog::notice(
      "export_cp_remove_order: Order $invoice_number was deleted",
      [
        'invoice_number'  => $invoice_number,
        'reason'          => $reason,
        'new_count_items' => $new_count_items,
        'sql'             => $sql,
        'res'             => $res
      ]
    );

  } else {

    GPLog::critical(
      "export_cp_remove_order: Order $invoice_number had dispensed items and could only be partially deleted",
      [
        'invoice_number'  => $invoice_number,
        'reason'          => $reason,
        'new_count_items' => $new_count_items
      ]
    );

  }
}

function export_cp_append_order_note($mssql, $invoice_number, $note) {
    $cp_automation_user = CAREPOINT_AUTOMATION_USER;

    $sql = "
      UPDATE csom 
      SET comments = RIGHT(CONCAT(comments, CHAR(10), '$note'), 255),
          chg_user_id = '{$cp_automation_user}'
      
      WHERE invoice_nbr = $invoice_number -- chg_user_id = @user_id, chg_date = @today
    ";

    $res = $mssql->run($sql);

    GPLog::notice(
      "export_cp_append_order_note: Order $invoice_number had note appended: $note",
      [
        'invoice_number'  => $invoice_number,
        'note'            => $note,
        'sql'             => $sql,
        'res'             => $res
      ]
    );
}

/* When moving order_items, we want to maintain item_added_by because if "added_manually" that will affect get_days_and_message() */
/* Moving order_items changes their primary key so in essence they are being deleted from one order and created in the other order
/*
    If called from orders-cp-created as expected, order_items will have a lot of changes: order_item_created when duplicate order is ,
    created order_item deleted when removed from duplicate order, order_item_created when added to the original orders, and maybe
    order_item deleted again if don't want to fill the item once it is moved to the original order.  Long term architecture question
    whether we want to be able to delete or modify change feeds (in this case order_items) based on what we did in another change feed
    (in the case orders_cp).  We should think threw the right design sooner rather than latter since this get harder and harder to fix
*/
function export_cp_merge_orders($from_invoice_number, $to_invoice_number) {

    global $mssql;
    $mssql = $mssql ?: new Mssql_Cp();

    $from_order_id = $from_invoice_number - 2; //TODO SUPER HACKY
    $to_order_id   = $to_invoice_number - 2; //TODO SUPER HACKY

    //Update chg_user_id as well so we know the Pharmacy App made this change
    $sql = "
        UPDATE csomline
        SET csomline.order_id = '{$to_order_id}'
        FROM csomline
        JOIN cprx ON cprx.rx_id = csomline.rx_id
        WHERE csomline.order_id = '{$from_order_id}'
        -- not sure how to handle if order_item is already dispensed so skip those for now
        AND rxdisp_id = 0
    ";

    $res = $mssql->run($sql);

    GPLog::notice(
      "export_cp_merge_orders: merging $from_invoice_number into $to_invoice_number",
      [
        'from_invoice_number' => $from_invoice_number,
        'to_invoice_number'   => $to_invoice_number,
        'sql' => $sql,
        'res' => $res
      ]
    );

    export_cp_remove_order($from_invoice_number, "Merged $from_invoice_number into $to_invoice_number");
}
