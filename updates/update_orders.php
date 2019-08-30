<?php

require_once 'changes/changes_to_orders.php';

function update_orders() {

  $changes = changes_to_orders("gp_orders_cp");

  $message = "CRON: update_orders ".print_r($changes, true);

  echo $message;

  mail('adam@sirum.org', "CRON: update_orders ", $message);

  if ( ! count($changes['deleted']+$changes['created']+$changes['updated'])) return;

  //TODO Differentiate between actual order that are to be sent out and
  // - Ones that were faxed/called in but not due yet
  // - Ones that were surescripted in but not due yet  [order_status] => Surescripts Fill
  // [order_status] => Surescripts Authorization Approved

  //TODO Upsert WooCommerce Order Status, Order Tracking

  //TODO Upsert Salseforce Order Status, Order Tracking

  //TODO Remove Delete Orders

}
