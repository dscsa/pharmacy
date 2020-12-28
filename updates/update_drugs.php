<?php


use Sirum\Logging\SirumLog;

function update_drugs($changes) {

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
  echo $msg;
  log_info("update_drugs: all changes. $msg", [
    'deleted_count' => $count_deleted,
    'created_count' => $count_created,
    'updated_count' => $count_updated
  ]);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  $loop_timer = microtime(true);

  foreach($changes['updated'] as $i => $updated) {

    SirumLog::$subroutine_id = "drugs-updated-".sha1(serialize($updated));

    SirumLog::debug(
      "update_drugs: Drugs Updated",
      [
          'updated' => $updated,
          'source'  => 'v2',
          'type'    => 'drugs',
          'event'   => 'updated'
      ]
    );

    if ($updated['price30'] != $updated['old_price30'] OR $updated['price90'] != $updated['old_price90']) {

      $created = "Created:".date('Y-m-d H:i:s');

      $salesforce = [
        "subject"   => "Drug Price Change for $updated[drug_name]",
        "body"      => "$item[drug_name] price $updated[old_price30] >>> $updated[price30], $updated[old_price90] >>> $updated[price90] $created",
        "assign_to" => "Kiah",
        "due_date"  => date('Y-m-d')
      ];

      $event_title = @$item['drug_name']." Drug Price Change $created";

      create_event($event_title, [$salesforce]);
    }

    if ($updated['drug_ordered'] && ! $updated['old_drug_ordered'])
      log_warning("new drug ordered", $updated);

    if ( ! $updated['drug_ordered'] && $updated['old_drug_ordered'])
      log_warning("drug stopped being ordered", $updated);

    if ($updated['drug_gsns'] != $updated['old_drug_gsns']) {

      //Delete Order item(s) so they are (re)created now that the GSN numbers will match
      $sql = "
        DELETE gp_order_items
        FROM gp_order_items
        JOIN gp_rxs_single
          ON gp_rxs_single.rx_number = gp_order_items.rx_number
        WHERE
          rx_dispensed_id IS NULL
          AND CONCAT(',', rx_gsn, ',') LIKE '%$updated[drug_gsns]%'
      ";

      $results = $mysql->run($sql)[0];

      log_warning("drug gsns changed.  deleting order_item(s) for them to be recreated and matched", [$sql, $results, $updated]);
    }
  }
  log_timer('drug-updated', $loop_timer, $count_updated);


  SirumLog::resetSubroutineId();


  //TODO Upsert WooCommerce Patient Info

  //TODO Upsert Salseforce Patient Info

  //TODO Consider Pat_Autofill Implications

  //TODO Consider Changing of Payment Method
}
