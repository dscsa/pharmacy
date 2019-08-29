<?php

require_once 'changes/changes_to_orders.php';

function update_orders() {

  $changes = changes_to_orders();

  mail('adam@sirum.org', "CRON: update_orders", print_r($changes, true));

  if ( ! count($changes['upserts']+$changes['removals'])) return;

  //TODO Upsert WooCommerce Order Status, Order Tracking

  //TODO Upsert Salseforce Order Status, Order Tracking
}
