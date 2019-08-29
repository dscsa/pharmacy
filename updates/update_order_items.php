<?php

require_once 'changes/changes_to_order_items';

function update_order_items() {

  $changes = changes_to_order_items();

  mail('adam@sirum.org', "CRON: update_order_items", print_r($changes, true));

  if ( ! count($changes['upserts']+$changes['removals'])) return;

  //TODO Calculate Qty, Days, & Price

  //TODO Pend v2 Inventory

  //TODO Update Invoices

  //TODO Update WooCommerce Order Total & Order Count & Order Invoice

  //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration

}
