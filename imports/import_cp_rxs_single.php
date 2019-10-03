<?php

require_once 'dbs/mssql_cp.php';
require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_cp_rxs_single() {

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();

  $rxs = $mssql->run("

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
      written_qty * refills_orig as qty_original,
      sig_text_english as sig_raw,

      autofill_yn as rx_autofill,
      CONVERT(varchar, orig_disp_date, 20) as refill_date_first,
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
        md_id,
        MAX(npi) as provider_npi,
        MAX(name_first) as provider_first_name,
        MAX(name_last) as provider_last_name,
        MAX(clinic_name) as provider_clinic,
        MAX(phone) as provider_phone
      FROM cpmd_spi
      WHERE state = 'GA'
      GROUP BY md_id
    ) as md ON
      cprx.md_id = md.md_id

    WHERE
      -- cprx.chg_date > @today - 7 AND -- Only recent scripts to cut down on the
      ISNUMERIC(script_no) = 1 AND  -- Can be NULL, Empty String, or VarChar. Highly correlated with script_status_cn > 0 but not exact.  We should figure out which one is better to use
      ISNULL(cprx.status_cn, 0) <> 3 AND
      (ISNULL(cprx.status_cn, 0) <> 2 OR last_transfer_type_io = 'O') -- NULL/0 is active, 1 is not yet dispensed?, 2 is transferred out/inactive, 3 is voided

  ");

  //log_info("
  //import_cp_rxs_single: rows ".count($rxs[0]));

  $keys = result_map($rxs[0],
    function($row) {
      //Clean Drug Name and save in database RTRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ISNULL(generic_name, cprx.drug_name), ' CAPSULE', ' CAP'),' CAPS',' CAP'),' TABLET',' TAB'),' TABS',' TAB'),' TB', ' TAB'),' HCL',''),' MG','MG'), '\"', ''))
      $row['drug_name'] = str_replace([' CAPSULE', ' CAPS', ' CP', ' TABLET', ' TABS', ' TB', ' HCL', ' MG', ' MEQ', ' MCG', ' ML', '\\"'], [' CAP', ' CAP', ' CAP', ' TAB', ' TAB', ' TAB', '', 'MG', 'MEQ', 'MCG', 'ML', ''], $row['drug_name']);
      $row['provider_phone'] = clean_phone($row['provider_phone']);

      if ( ! $row['sig_raw']) {
        mail('adam@sirum.org', "CRON: Blank Sig on Rx ", print_r($row, true));
        $row['sig_raw'] = '';
      }

      //Some validations
      assert_length($row, 'provider_phone', 12);  //no delimiters with single quotes

      return $row;
    }
  );

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_rxs_single_cp');

  $mysql->run("INSERT INTO gp_rxs_single_cp $keys VALUES ".$rxs[0]);
}
