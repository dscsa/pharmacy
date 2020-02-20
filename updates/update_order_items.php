<?php

require_once 'changes/changes_to_order_items.php';
require_once 'helpers/helper_days_dispensed.php';
require_once 'exports/export_cp_order_items.php';
require_once 'exports/export_v2_order_items.php';
//require_once 'exports/export_gd_transfer_fax.php';

function update_order_items() {

  $changes = changes_to_order_items('gp_order_items_cp');

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_order_items: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  function get_full_item($item, $mysql, $mssql) {

    if ( ! $item['invoice_number'] OR ! $item['rx_number']) {
      log_error('ERROR get_full_item: missing invoice_number or rx_number', get_defined_vars());
      return [];
    }

    /* ORDER MAY HAVE NOT BEEN ADDED YET
    JOIN gp_orders ON
      gp_orders.invoice_number = gp_order_items.invoice_number
    */

    $sql = "
      SELECT *
      FROM
        gp_order_items
      JOIN gp_rxs_single ON
        gp_order_items.rx_number = gp_rxs_single.rx_number
      JOIN gp_rxs_grouped ON
        rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
      JOIN gp_patients ON
        gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
      LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
        gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
      WHERE
        gp_order_items.invoice_number = $item[invoice_number] AND
        gp_order_items.rx_number = $item[rx_number]
    ";


    $full_item  = [];
    $query = $mysql->run($sql);

    if (isset($query[0][0])) {
      $full_item = $query[0][0];

      if ( ! $full_item['drug_generic'])
        log_error($full_item['rx_gsn'] ? 'get_full_item: Add GSN to V2!' : 'get_full_item: Missing GSN!', "$full_item[invoice_number] $full_item[drug_name] $full_item[rx_number] $full_item[rx_gsn]  $full_item[drug_gsns]");

    } else {

      $debug = "
        SELECT
          gp_order_items.rx_number as has_gp_order_items,
          gp_rxs_grouped.rx_numbers as has_gp_rxs_grouped,
          gp_rxs_single.rx_number as has_gp_rxs_single,
          gp_patients.patient_id_cp as has_gp_patients,
          gp_stock_live.drug_generic as has_gp_stock_live
        FROM
          gp_order_items
        LEFT JOIN gp_rxs_grouped ON
          rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
        LEFT JOIN gp_rxs_single ON
          gp_order_items.rx_number = gp_rxs_single.rx_number
        LEFT JOIN gp_patients ON
          gp_rxs_grouped.patient_id_cp = gp_patients.patient_id_cp
        LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
          gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
        WHERE
          gp_order_items.invoice_number = $item[invoice_number] OR
          gp_order_items.rx_number = $item[rx_number]
      ";

      $missing_table = $mysql->run($debug);

      $rx_in_cp = $mssql->run("

          DECLARE @today as DATETIME
          SET @today = GETDATE()

          SELECT
            script_no as rx_number,
            pat_id as patient_id_cp,
            drug_name as drug_name,
            cprx.gcn_seqno as rx_gsn,

            DATEDIFF(day, @today, expire_date) as days_left,
            (CASE WHEN script_status_cn = 0 AND expire_date > @today THEN refills_left ELSE 0 END) as refills_left,
            refills_orig + 1 as refills_original,
            (CASE WHEN script_status_cn = 0 AND expire_date > @today THEN written_qty * refills_left ELSE 0 END) as qty_left,
            written_qty * (refills_orig + 1) as qty_original,
            sig_text_english as sig_raw,

            autofill_yn as rx_autofill,
            CONVERT(varchar, COALESCE(orig_disp_date, dispense_date), 20) as refill_date_first,
            CONVERT(varchar, dispense_date, 20) as refill_date_last,
            (CASE
              WHEN script_status_cn = 0 AND autofill_resume_date >= @today
              THEN CONVERT(varchar, autofill_resume_date, 20)
              ELSE NULL END
            ) as refill_date_manual,
            CONVERT(varchar, dispense_date + disp_days_supply, 20) as refill_date_default,

            script_status_cn as rx_status,
            ISNULL(IVRCmt, 'Entered') as rx_stage,
            csct_code.name as rx_source,
            last_transfer_type_io as rx_transfer,

            provider_npi,
            provider_first_name,
            provider_last_name,
            provider_clinic,
            provider_phone,

            CONVERT(varchar, cprx.chg_date, 20) as rx_date_changed,
            CONVERT(varchar, expire_date, 20) as rx_date_expired

          FROM cprx

          LEFT JOIN cprx_disp ON
            cprx_disp.rxdisp_id = last_rxdisp_id

          LEFT JOIN csct_code ON
            ct_id = 194 AND code_num = input_src_cn

          LEFT JOIN (

            SELECT
              --Service Level MOD 2 = 1 means accepts SureScript Refill Reques
              -- STUFF == MSSQL HACK TO GET MOST RECENTLY UPDATED ROW THAT ACCEPTS SURESCRIPTS
              md_id,
              STUFF(MAX(CONCAT(ServiceLevel % 2, last_modified_date, npi)), 1, 23, '') as provider_npi,
              STUFF(MAX(CONCAT(ServiceLevel % 2, last_modified_date, name_first)), 1, 23, '') as provider_first_name,
              STUFF(MAX(CONCAT(ServiceLevel % 2, last_modified_date, name_last)), 1, 23, '') as provider_last_name,
              STUFF(MAX(CONCAT(ServiceLevel % 2, last_modified_date, clinic_name)), 1, 23, '') as provider_clinic,
              STUFF(MAX(CONCAT(ServiceLevel % 2, last_modified_date, phone)), 1, 23, '') as provider_phone
            FROM cpmd_spi
            WHERE cpmd_spi.state = 'GA'
            GROUP BY md_id

          ) as md ON
            cprx.md_id = md.md_id

          WHERE cprx.script_no = $item[rx_number]
      ");

      log_error("Missing Order Item!", get_defined_vars());
    }

    //log_info("Get Full Item", get_defined_vars());

    return $full_item;
  }

  //If just added to CP Order we need to
  //  - determine "days_dispensed_default" and "qty_dispensed_default"
  //  - pend in v2 and save applicable fields
  //  - if first line item in order, find out any other rxs need to be added
  //  - update invoice
  //  - update wc order total
  foreach($changes['created'] as $created) {

    $item = get_full_item($created, $mysql, $mssql);

    if ( ! $item) {
      log_error("Created Item Missing", $created);
      continue;
    }

    if ($item['days_dispensed_actual']) {

      log_error("order_item created but days_dispensed_actual already set", $item);

      set_price_refills_actual($item, $mysql);
      continue;
    }

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just deleted from CP Order we need to
  //  - set "days_dispensed_default" and "qty_dispensed_default" to 0
  //  - unpend in v2 and save applicable fields
  //  - if last line item in order, find out any other rxs need to be removed
  //  - update invoice
  //  - update wc order total
  foreach($changes['deleted'] as $deleted) {

    $item = get_full_item($deleted, $mysql, $mssql);

    if ( ! $item) {
      log_error("Deleted Item Missing", get_defined_vars());
      continue;
    }

    $item = set_days_default($item, 0, '', $mysql);

    export_v2_remove_pended($item);

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }

  //If just updated we need to
  //  - see which fields changed
  //  - think about what needs to be updated based on changes
  foreach($changes['updated'] as $updated) {

    $item = get_full_item($updated, $mysql, $mssql);

    if ( ! $item) {
      log_error("Updated Item Missing", get_defined_vars());
      continue;
    }

    $changed_fields = changed_fields($updated);

    if ($item['days_dispensed_actual']) {

      set_price_refills_actual($item, $mysql);


      if ($item['days_dispensed_actual'] == $item['days_dispensed_default']) {
        log_info("days_dispensed_actual was set", [$updated, $changed_fields]);
      } else {
        log_error("days_dispensed_default was wrong: $item[days_dispensed_default] >>> $item[days_dispensed_actual]", ['item' => $item, 'updated' => $updated, 'changed' => $changed_fields]);
      }

    } else if ($updated['item_added_by'] == 'MANUAL' AND $updated['old_item_added_by'] != 'MANUAL') {

      log_info("Cindy deleted and readded this item", [$updated, $changed_fields]);

    } else if ( ! $item['days_dispensed_default']) {

      log_error("Updated Item has no days_dispensed_default.  Was GSN added?", get_defined_vars());

    } else {
      log_info("Updated Item No Action", get_defined_vars());
    }

    log_info("update_order_items", get_defined_vars());

    //TODO Update Salesforce Order Total & Order Count & Order Invoice using REST API or a MYSQL Zapier Integration
  }
}
