<?php

use Sirum\Logging\SirumLog;

/**
 * Pull in the CarePoint order items
 * @return void
 */
function import_cp_order_items() {

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();

  $items = $mssql->run("

    DECLARE @today as DATETIME
    SET @today = GETDATE()

    SELECT
      csomline.order_id+2 as invoice_number, -- Important note the difference of 2 has been stable for a while (but maybe we should do a JOIN?) But for invoices <1976 the difference was 4 and for invoices 1976<=X<=2676 the difference was 3
      MAX(drug_name) as drug_name,
      MAX(cprx.pat_id) as patient_id_cp,
  		COALESCE(
        MIN(CASE
          WHEN refills_left > .1 THEN script_no
          ELSE NULL END
        ),
        MAX(script_no)
      ) as rx_number, --If multiple of same drug in order, pick the oldest one with refills.  If none have refills use the newest. See dbo.csuser for user ids
      MAX(disp.rxdisp_id) as rx_dispensed_id, --Hacky, most recent line item might not line up with the rx number we are filling
      MAX(dispense_qty) as qty_dispensed_actual,
      MAX(disp_days_supply) as days_dispensed_actual,
      COUNT(*) as count_lines,
      CONVERT(varchar, MAX(csomline.add_date), 20) as item_date_added,
      MAX(CASE
        WHEN CsOmLine.add_user_id = 901  THEN 'HL7'
        WHEN CsOmLine.add_user_id = 902  THEN 'AUT'
        WHEN CsOmLine.add_user_id = 1002 THEN 'AUTOFILL'
        WHEN CsOmLine.add_user_id = 1003 THEN 'WEBFORM'
        WHEN CsOmLine.add_user_id = 1004 THEN 'REFILL REQUEST'
        -- cph.csuser: 1018 CK, 130 Admin
        ELSE 'MANUAL' END
      ) as item_added_by -- from csuser
  	FROM csomline
  	JOIN cprx ON
      cprx.rx_id = csomline.rx_id
    JOIN csom ON
      csom.order_id = csomline.order_id
    LEFT JOIN cprx_disp disp ON
      csomline.rxdisp_id > 0 AND disp.rxdisp_id = csomline.rxdisp_id -- Rx might not yet be dispensed
    WHERE
      ISNULL(csom.status_cn, 1) <> 3 AND ( --most of time guardian deletes order_items but sometimes it leaves them even if order is deleted?  join csom since doesn't seem to be reflected in csomline.line_status_cn or line_state_cn e.g. 55073-55079 (order_id 55071-55077)
        dispense_date IS NULL OR
        dispense_date > @today - ".DAYS_OF_ITEMS_TO_IMPORT."  --Undispensed and dispensed within the week only to cut down volume. i think this still enables qty/days_dispensed_actual to be set properly
      )
    GROUP BY
      csomline.order_id,
      (CASE WHEN gcn_seqno > 0 THEN gcn_seqno ELSE script_no END) --This is because of Orders like 8660 where we had 4 duplicate Citalopram 40mg.  Two that were from Refills, One Denied Surescript Request, and One new Surescript.  We are only going to send one GCN so don't list it multiple times
  ");

  if ( ! $items[0] OR ! count($items[0])) return SirumLog::alert('No Cp Order Items to Import', get_defined_vars());

  $keys = result_map($items[0]);
  $mysql->replace_table("gp_order_items_cp", $keys, $items[0]);
}
