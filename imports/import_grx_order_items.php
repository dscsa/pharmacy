<?php

require '../dbs/mssql_grx.php';
require '../dbs/mysql_webform.php';
require '../helpers/replace_empty_with_null.php';

function import_grx_order_items() {

  $mssql = new Mssql_Grx();
  $mysql = new Mysql_Webform();

  $order_items = $mssql->run("

    SELECT
  		MIN(CONCAT(order_id+2, '-', line_id, '-', rxdisp_id, '-', line_status_cn, '-', line_state_cn)) as in_order, --Add 2 so that we get invoice_nbr.  This is so that when we search email logs we only get invoice_nbr matches and not order_id ones
  		order_id as ordered_order_id,
  		(CASE WHEN gcn_seqno > 0 THEN gcn_seqno ELSE script_no END) as ordered_gcn_or_script,
  		MAX(name_first) as provider_fname,
  		MAX(name_last) as provider_lname,
  		MAX(npi) as npi,
  		MAX(phone) as provider_phone,
  		COALESCE(MIN(CASE WHEN refills_left > .1 THEN script_no ELSE NULL END), MAX(script_no)) as ordered_script_no, --If multiple of same drug in order, pick the oldest one with refills.  If none have refills use the newest. See dbo.csuser for user ids
  		MAX(CASE WHEN CsOmLine.add_user_id = 901 THEN 'HL7' WHEN CsOmLine.add_user_id = 902 THEN 'AUT' WHEN CsOmLine.add_user_id = 1002 THEN 'AUTOFILL' WHEN CsOmLine.add_user_id = 1003 THEN 'WEBFORM' WHEN CsOmLine.add_user_id = 1004 THEN 'REFILL REQUEST' ELSE 'MANUAL' END) as added_to_order_by -- from csuser
  	FROM csomline (nolock)
  	JOIN cprx ON cprx.rx_id = csomline.rx_id
  	LEFT OUTER JOIN cpmd_spi (nolock) on cpmd_spi.state = 'GA' AND cprx.md_id = cpmd_spi.md_id
  	GROUP BY order_id, (CASE WHEN gcn_seqno > 0 THEN gcn_seqno ELSE script_no END) --This is because of Orders like 8660 where we had 4 duplicate Citalopram 40mg.  Two that were from Refills, One Denied Surescript Request, and One new Surescript.  We are only going to send one GCN so don't list it multiple times

  ");

  $keys = array_keys($order_items[0]);
  $vals = replace_empty_with_null($order_items, [
    'autofill_date',
    'expire_date',
    'oldest_script_high_refills',
    'oldest_script_with_refills',
    'oldest_active_script',
    'newest_script'
  ]);

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_order_items_grx');

  $mysql->run("
    INSERT INTO gp_order_items_grx ".$keys." VALUES ".implode(',', $vals)
  );
}
