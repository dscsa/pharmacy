<?php

require_once 'dbs/mssql_grx.php';
require_once 'dbs/mysql_webform.php';
require_once 'helpers/helpers_db.php';

function import_grx_rxs_single() {

  $mssql = new Mssql_Grx();
  $mysql = new Mysql_Webform();

  $rxs = $mssql->run("

    DECLARE @today as DATETIME
    SET @today = GETDATE()

    SELECT
      script_no as rx_number,
      pat_id as guardian_id,
      ISNULL(generic_name, drug_name) as drug_name_generic,
      drug_name_raw,
      cprx.gcn_seqno as gcn,

      CASE WHEN script_status_cn = 0 AND expire_date > @today THEN refills_left ELSE 0 END as refills_left,
      refills_orig + 1 as refills_original,
      written_qty as qty_written,
      sig_text_english as sig_raw,

      rx_autofill,
      autofill_date as refill_date_manual,
      dispense_date + disp_days_supply as refill_date_default,

      script_status_cn as rx_status,
      ISNULL(IVRCmt, 'Entered') as rx_stage,
      csct_code.name as rx_source,
      last_transfer_type_io as transfer,

      provider_npi,
      provider_fname as provider_first_name,
      provider_lname as provider_last_name,
      provider_phone,

      orig_disp_date as dispense_date_first,
      dispense_date as dispense_date_last,
      chg_date as rx_change_date,
      expire_date

  	FROM cprx
    LEFT JOIN cpmd_spi on cpmd_spi.state = 'GA' AND cprx.md_id = cpmd_spi.md_id
  	LEFT JOIN (
  		SELECT STUFF(MIN(gni+fdrndc.ln), 1, 1, '') as generic_name, fdrndc.gcn_seqno -- STUFF is a hack to get first occurance since MSSQL doesn't have that ability
  		FROM fdrndc
  		GROUP BY fdrndc.gcn_seqno
  	) as generic_name ON generic_name.gcn_seqno = cprx.gcn_seqno
    WHERE status_cn <> 3 AND (status_cn <> 2 OR last_transfer_type_io = 'O') -- NULL/0 is active, 1 is not yet dispensed?, 2 is transferred out/inactive, 3 is voided

  ");

  $keys = array_keys($rxs[0][0]);
  $vals = escape_vals($rxs[0]);

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_rxs_single_grx');

  $mysql->run("
    INSERT INTO gp_rxs_single_grx (".implode(',', $keys).") VALUES ".implode(',', $vals)
  );
}
