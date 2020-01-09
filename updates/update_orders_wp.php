<?php

require_once 'changes/changes_to_orders_wp.php';

function update_orders_wp() {

  $changes = changes_to_orders_wp("gp_orders_wp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_orders: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();

  foreach($changes['created'] as $created) {

    log_notice("Created Order WC", get_defined_vars());

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  foreach($changes['deleted'] as $deleted) {

    log_notice("Deleted Order WC", get_defined_vars());

  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    log_notice("Updated Order WC", get_defined_vars());

  }
}
