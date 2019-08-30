<?php

require_once 'dbs/mssql_cp.php';
require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_cp_orders() {

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();

  $orders = $mssql->run("

    SELECT
      invoice_nbr as invoice_number,
      pat_id as patient_id_cp,
      ustate.name as order_source,
      ostate.name as order_stage,
      ostatus.descr as order_status,
      ship_addr1 as order_address1,
      ship_addr2 as order_address2,
      ship_city as order_city,
      ship_state_cd as order_state,
      LEFT(ship_zip, 5) as order_zip,
      csom_ship.tracking_code as tracking_number,
      CONVERT(varchar, add_date, 20) as order_date_added,
      CONVERT(varchar, csom.ship_date, 20) as order_date_dispensed,
      CONVERT(varchar, ship.ship_date, 20) as order_date_shipped,
      CONVERT(varchar, chg_date, 20) as order_date_changed
    FROM csom
      LEFT JOIN cp_acct ON cp_acct.id = csom.acct_id
      LEFT JOIN csct_code as ostate on (ostate.ct_id = 5000 and (isnull(csom.order_state_cn,0) = ostate.code_num))
      LEFT JOIN csct_code as ustate on (ustate.ct_id = 5007 and (isnull(csom.order_category_cn,0) = ustate.code_num))
      LEFT JOIN csomstatus as ostatus on (csom.order_state_cn = ostatus.state_cn and csom.order_status_cn = ostatus.omstatus)
      LEFT JOIN (SELECT order_id, MAX(ship_date) as ship_date FROM CsOmShipUpdate GROUP BY order_id) ship ON csom.order_id = ship.order_id -- CSOM_SHIP didn't always? update the tracking number within the day so use CsOmShipUpdate which is what endicia writes
      LEFT JOIN csom_ship ON csom.order_id = csom_ship.order_id -- CsOmShipUpdate won't have tracking numbers that Cindy inputted manually
    WHERE
      pat_id IS NOT NULL -- Some GRX orders have no patient!?
  ");



  $keys = array_keys($orders[0][0]);
  $vals = result_map($orders[0],
    function($row) {
      return "(".implode(', ', $row);.")";
    }
  );

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_orders_cp');

  $mysql->run("
    INSERT INTO gp_orders_cp (".sort_cols($row).") VALUES ".implode(',', $vals)
  );
}

/*
Cast(CASE WHEN script_status_cn = 0 AND rx.expire_date > @today THEN rx.refills_left ELSE 0 END as float) as refills_left,
refills_total,
Cast(rx.refills_orig + 1 as float) as refills_orig,
Cast(rx.written_qty as float) as written_qty,
Cast(rx.days_supply as float) as written_days,
Cast(dispense_qty as float) as dispense_qty,
Cast(disp_days_supply as float) as days_supply,
Cast(dispense_date as date) as last_dispense_date,
rx_autofill,
autofill_date,
ISNULL(NULL, dispense_date + disp_days_supply) as refill_date, --TODO replace NULL with refill_date once the 80% are gone
Cast(rx.orig_disp_date as date) as orig_disp_date,
CONCAT('"', rx.sig_text_english, '"') as sig_text, -- don't mess up our CSV format

CONCAT('"', rxprofile_drug_name, '"') as drug_name, --Sometimes FDRNDC cannot be found because Cindy's entered drug has GCNSEQ_NO of 0 (Order 3659) so use rx.drug_name instead
(CASE WHEN script_status_cn <> 1
  THEN (CASE WHEN  @today - orig_disp_date > 2 THEN 'Refill' ELSE ISNULL(rx.IVRCmt, 'Entered') END)
  ELSE (CASE WHEN last_transfer_type_io = 'O'
    THEN 'Transferred Out'
    ELSE (CASE WHEN rxprofile_drug_name IS NULL
      THEN 'No Drug'
      ELSE 'Inactive'
      END)
    END)
  END) as script_status,
csct_code.name as rx_source,
added_to_order_by,
rxprofile_gcn_seqno as gcn_seqno, -- use this one rather than rx.gcn_seqno in case of "0" values
rx.script_no,
rx.rx_id,
ordered_script_no,
oldest_script_high_refills, -- for debugging purposes
oldest_script_with_refills, -- for debugging purposes
oldest_active_script, -- for debugging purposes
newest_script,				-- for debugging purposes
in_order,
rxprofile.expire_date,
rx.script_status_cn,
npi,
provider_fname,
provider_lname,
provider_phone
LEFT OUTER JOIN cprx_disp disp (nolock) ON disp.rxdisp_id = last_rxdisp_id
LEFT OUTER JOIN cprx rx (nolock) ON rx.script_no = COALESCE(ordered_script_no, oldest_script_high_refills, oldest_script_with_refills, oldest_active_script, newest_script) --use ordered_rx if possible otherwise use oldest
LEFT OUTER JOIN csct_code ON ct_id = 194 AND code_num = input_src_cn
(SELECT COUNT(*) FROM cprx WHERE cprx.pat_id = pat.pat_id AND orig_disp_date < @today - 4 AND cprx.gcn_seqno = rx.gcn_seqno) as is_refill,
*/
