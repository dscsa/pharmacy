<?php

use Sirum\Logging\SirumLog;

function load_full_item($partial, $mysql, $overwrite_rx_messages = false) {

  if ( ! $partial['rx_number']) {
    log_error('ERROR get_full_item: missing rx_number', get_defined_vars());
    return [];
  }

  if ($partial['invoice_number']) //E.g. if changing days_dispensed_actual NULL >>> 90, then this will be true and order will be shipped
    $past_orders = "gp_order_items.invoice_number = $partial[invoice_number]";
  else //If no invoice number specified only show current orders
    $past_orders = "gp_order_items.rx_dispensed_id IS NULL";

  $sql = "
    SELECT
      *,
      gp_rxs_grouped.*,
      gp_orders.invoice_number,
      gp_order_items.invoice_number as dontuse_item_invoice,
      gp_orders.invoice_number as dontuse_order_invoice
    FROM
      gp_rxs_single
    JOIN gp_patients ON
      gp_rxs_single.patient_id_cp = gp_patients.patient_id_cp
    JOIN gp_rxs_grouped ON
      rx_numbers LIKE CONCAT('%,', gp_rxs_single.rx_number, ',%')
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
    LEFT JOIN gp_order_items ON -- choice to show any order_item from this rx_group and not just if this specific rx matches
      $past_orders AND
      rx_numbers LIKE CONCAT('%,', gp_order_items.rx_number, ',%')
    LEFT JOIN gp_orders ON -- ORDER MAY HAVE NOT BEEN ADDED YET
      gp_orders.invoice_number = gp_order_items.invoice_number
    WHERE
      gp_rxs_single.rx_number = $partial[rx_number]
  ";

  $query = $mysql->run($sql);

  if (isset($query[0][0])) {

    $item = $query[0][0];

    if ( ! $item['drug_generic']) {
      log_warning(($item['rx_gsn'] ? 'get_full_item: Add GSN to V2!' : 'get_full_item: Missing GSN!')." Invoice Number:$item[invoice_number] Drug:$item[drug_name] Rx:$item[rx_number] GSN:$item[rx_gsn] GSNS:$item[drug_gsns]", ['item' => $item, 'partial' => $partial, 'sql' => $sql]);
    }

    if (@$partial['invoice_number'] AND $partial['invoice_number'] != $item['invoice_number']) { //$partial[invoice_number] won't be set if called from update_rxs_single

      $debug = $mysql->run("
        SELECT * FROM gp_orders WHERE invoice_number = $partial[invoice_number]
      ");

      SirumLog::error(
        "helper_full_item: invoice_number retrieved ($item[invoice_number]) != invoice_number provided ($partial[invoice_number])", [
          'item' => $item,
          'partial' => $partial,
          'sql' => $sql,
          'debug' => $debug,
          'note' => "This can happen in order-items-deleted because the order_item used to have an Order but doesn't any longer"
        ]
      );
    }

    if (@$partial['invoice_number'] AND $item['item_date_added'] AND ! $item['invoice_number']) {
      $debug = $mysql->run("
        SELECT * FROM gp_orders WHERE invoice_number = $partial[invoice_number]
      ");
      log_error("get_full_item: item_date_added but no invoice number?  small chance that order has not been imported yet", ['item' => $item, 'partial' => $partial, 'sql' => $sql, 'debug' => $debug]);
    }

    if ( ! $item['item_date_added'] AND $item['invoice_number']) {
      log_error("get_full_item: no item_date_added but invoice number?  is this an old invoice_number from order_items", ['item' => $item, 'partial' => $partial, 'sql' => $sql]);
    }

    $full_item = add_full_fields([$item], $mysql, $overwrite_rx_messages)[0];

    return $full_item;

  }

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
      gp_rxs_single.patient_id_cp = gp_patients.patient_id_cp
    LEFT JOIN gp_stock_live ON -- might not have a match if no GSN match
      gp_rxs_grouped.drug_generic = gp_stock_live.drug_generic
    WHERE
      gp_order_items.rx_number = $partial[rx_number]
  ";

  $missing_table = $mysql->run($debug);

  $mssql = new Mssql_Cp();

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
        sig_text_english as sig_actual,

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

      WHERE cprx.script_no = '$partial[rx_number]'
  ");

  SirumLog::alert(
    "CANNOT GET FULL_ITEM! MOST LIKELY WILL NOT BE PENDED IN V2",
    [
      'overwrite_rx_messages' => $overwrite_rx_messages,
      'partial' => $partial,
      'missing_table' => $missing_table,
      'rx_in_cp' => $rx_in_cp
    ]
  );
  //log_info("Get Full Item", get_defined_vars());
}
