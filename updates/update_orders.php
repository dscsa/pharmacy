<?php

require_once __DIR__.'/../changes/changes_to_orders';

function update_orders() {

  $changes = changes_to_orders();

  mail('adam@sirum.org', "CRON: update_orders", print_r($changes, true));

  if ( ! count($changes['upserts']+$changes['removals'])) return;

  //TODO Upsert WooCommerce Order Status, Order Tracking

  //TODO Upsert Salseforce Order Status, Order Tracking
}
