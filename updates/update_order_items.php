<?php

require_once 'changes/changes_to_order_items.php';
require_once 'helpers/helper_days_dispensed.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
require_once 'exports/export_gd_orders.php';
require_once 'exports/export_wc_orders.php';

function update_order_items() {

  $changes = changes_to_order_items('gp_order_items_cp');

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "update_order_items $count_deleted deleted, $count_created created, $count_updated updated. ";

  echo $message;

  mail('adam@sirum.org', "CRON: $message", $message.print_r($changes, true));

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  function join_all_tables($order_item, $mysql) {

    $sql = "
      SELECT *
      FROM
        gp_order_items
      JOIN gp_rxs_single ON
        gp_order_items.rx_number = gp_rxs_single.rx_number
      JOIN gp_rxs_grouped ON
        rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
      JOIN gp_stock_live ON
        gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
      JOIN gp_patients ON
        gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
      WHERE
        rx_number = $order_item[rx_number]
    ";

    echo "
    $sql
    ";

    return $mysql->run($sql)[0];
  }
  /* Example

  [invoice_number] => 18513
            [rx_number] => 6009999
            [rx_dispensed_id] =>
            [days_dispensed_default] =>
            [days_dispensed_actual] =>
            [qty_dispensed_default] =>
            [qty_dispensed_actual] =>
            [qty_pended_total] =>
            [qty_pended_repacks] =>
            [count_pended_total] =>
            [count_pended_repacks] =>
            [item_status] =>
            [item_type] =>
            [item_date_added] => 2019-09-03 11:55:25
            [item_added_by] => HL7
            [patient_id_cp] => 4113
            [drug_generic] => Methocarbamol 500mg
            [drug_brand] => Robaxin
            [drug_name_raw] => ROBAXIN 500 MG TABLET
            [sig_qty_per_day] => 1.000
            [max_gsn] => 4654
            [drug_gsns] => ,4654,
            [refills_left] => 5.00
            [rx_autofill] => 0.00
            [refill_date_first] => 2018-11-28
            [refill_date_last] => 2019-02-14
            [refill_date_next] =>
            [refill_date_manual] =>
            [refill_date_default] => 2019-05-15
            [refill_date_target] =>
            [refill_target_days] =>
            [refill_target_count] =>
            [rx_numbers] => ,6009999,6009998,6009997,
            [rx_date_changed] => 2019-09-03 11:55:25
            [rx_date_expired] => 2020-09-02
            [message_display] =>
            [stock_level] => OUT OF STOCK
            [price_per_month] => 2
            [drug_ordered] => 1
            [qty_repack] =>
            [qty_inventory] => 4856.0
            [qty_entered] => 24944.0
            [qty_dispensed] => 3740.0
            [stock_threshold] =>
            [first_name] => Patient First
            [last_name] => Patient Last
            [birth_date] => 1985-03-10
            [phone1] => 9999999999
            [phone2] =>
            [email] => patient@gmail.com
            [patient_autofill] => 1
            [pharmacy_name] => Ingles Pharmacy #105
            [pharmacy_npi] => 1437315942
            [pharmacy_fax] => 7705376966
            [pharmacy_phone] => 7705379501
            [pharmacy_address] => 2865 Bremen Mt Zi
            [card_type] => Visa
            [card_last4] => 9999
            [card_date_expired] => 2023-04-30
            [billing_method] => PAY BY CARD: Visa 7097
            [billing_coupon] =>
            [patient_address1] => 99 Patient Ln
            [patient_address2] =>
            [patient_city] => City
            [patient_state] => GA
            [patient_zip] => 30110
            [total_fills] => 13
            [patient_status] => 1
            [lang] => EN
            [patient_date_added] => 2018-11-27 09:32:21
            [patient_date_changed] => 2019-08-26 00:00:00
  */

  //If just added to CP Order we need to
  //  - determine "days_dispensed_default" and "qty_dispensed_default"
  //  - pend in v2 and save applicable fields
  //  - if first line item in order, find out any other rxs need to be added
  //  - update invoice
  //  - update wc order total
  foreach($changes['created'] as $created) {

    $order_item = join_all_tables($created, $mysql);

    $days = get_days_dispensed($order_item);

    if ( ! $days)
      return export_cp_remove_item($order_item);

    set_days_dispensed($days);

    export_v2_add_pended($order_item);

    export_cp_add_more_items($order_item); //this will cause another update and we will end back in this loop

    export_gd_update_invoice($order_item);

    export_wc_update_order($order_item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    $order_item = join_all_tables($deleted, $mysql);

    set_days_dispensed(0);

    export_v2_remove_pended($order_item);

    export_cp_remove_more_items($order_item); //this will cause another update and we will end back in this loop

    export_gd_update_invoice($order_item);

    export_wc_update_order($order_item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    echo print_r($updated, true);

    $order_item = join_all_tables($updated, $mysql);
    //Probably finalized days/qty_dispensed_actual
    //Update invoice now or wait until shipped order?
    export_gd_update_invoice($order_item);

    export_wc_update_order($order_item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }
}
