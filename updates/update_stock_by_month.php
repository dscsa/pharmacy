<?php
require_once 'changes/changes_to_stock_by_month.php';
require_once 'dbs/mysql_wc.php';

function update_stock_by_month() {

  $changes = changes_to_stock_by_month("gp_stock_by_month_v2");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "
  update_stock_by_month: $count_deleted deleted, $count_created created, $count_updated updated. ";

  if ($count_deleted+$count_created+$count_updated)
    log_info($message.print_r($changes, true));

  //mail('adam@sirum.org', "CRON: $message", $message.print_r($changes, true));

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  $mysql->run('TRUNCATE TABLE gp_stock_live');

  $mysql->run("
    INSERT INTO gp_stock_live
    SELECT
      stock.drug_generic,
      MAX(drug_brand) as drug_brand,
      MAX(message_display) as message_display,
      NULL as stock_level,
      MAX(COALESCE(price30, price90/3)) as price_per_month,
      MAX(drug_ordered) as drug_ordered,
      MAX(qty_repack) as qty_repack,
      MAX(inventory.inventory_sum) as qty_inventory,
      SUM(stock.entered_sum) as qty_entered,
      SUM(stock.dispensed_sum) as qty_dispensed,
      MAX(inventory.inventory_sum) / (100*POWER(GREATEST(SUM(stock.dispensed_sum), MAX(qty_repack)), 1.1) / POWER(1+SUM(stock.entered_sum), .6)) as stock_threshold
    FROM
      gp_stock_by_month as stock
    JOIN gp_drugs ON
      gp_drugs.drug_generic = stock.drug_generic
    JOIN gp_stock_by_month as inventory ON
      stock.drug_generic = inventory.drug_generic AND
      YEAR(inventory.month)  = YEAR(CURDATE() + INTERVAL 1 MONTH) AND
      MONTH(inventory.month) = MONTH(CURDATE() + INTERVAL 1 MONTH)
    WHERE
      YEAR(stock.month)  >= YEAR(CURDATE() - INTERVAL 3 MONTH) AND
      MONTH(stock.month) >= MONTH(CURDATE() - INTERVAL 3 MONTH)
    GROUP BY
      stock.drug_generic
  ");

  $mysql->run("
    UPDATE gp_stock_live
    SET stock_level = CASE
      WHEN drug_ordered IS NULL THEN '".STOCK_LEVEL['NOT OFFERED']."'
      WHEN stock_threshold > 1.0 THEN '".STOCK_LEVEL['HIGH SUPPLY']."'
      WHEN stock_threshold > 0.7 THEN '".STOCK_LEVEL['LOW SUPPLY']."'
      WHEN price_per_month >= 20 AND qty_dispensed = 0 AND qty_inventory > 5*qty_repack THEN '".STOCK_LEVEL['ONE TIME']."'
      WHEN qty_inventory > qty_repack THEN '".STOCK_LEVEL['REFILL ONLY']."'
      ELSE '".STOCK_LEVEL['OUT OF STOCK']."'
    END
  ");

  //TODO Calculate Qty Per Day from Sig and save in database

  //TODO Clean Drug Name and save in database RTRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ISNULL(generic_name, cprx.drug_name), ' CAPSULE', ' CAP'),' CAPS',' CAP'),' TABLET',' TAB'),' TABS',' TAB'),' TB', ' TAB'),' HCL',''),' MG','MG'), '\"', ''))

  //TODO Implement rx_status logic that was in MSSQL Query and Save in Database

  //TODO Maybe? Update Salesforce Objects using REST API or a MYSQL Zapier Integration

  //TODO THIS NEED TO BE UPDATED TO MYSQL AND TO INCREMENTAL BASED ON CHANGES

  //TODO Add Group by "Qty per Day" so its GROUP BY Pat Id, Drug Name,
  //WE WANT TO INCREMENTALLY UPDATE THIS TABLE RATHER THAN DOING EXPENSIVE GROUPING QUERY ON EVERY READ
  /*$mysql->run("

    DECLARE @today as DATETIME
    SET @today = GETDATE()

    SELECT
  		pat_id as guardian_id,
  		RTRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ISNULL(generic_name, cprx.drug_name), ' CAPSULE', ' CAP'),' CAPS',' CAP'),' TABLET',' TAB'),' TABS',' TAB'),' TB', ' TAB'),' HCL',''),' MG','MG'), '\"', '')) as drug_name, --MAKESHIFT REGEX https://stackoverflow.com/questions/21378193/regex-pattern-inside-sql-replace-function
  		CAST(SUM(CASE WHEN script_status_cn = 0 AND expire_date > @today THEN refills_left ELSE 0 END) as float) as refills_total,  -- Though this was always true but Rx 6000760 was expired with a 0 status
  		MAX(cprx.gcn_seqno) as gcn_seqno, --MIN could give 0 GCN like Order 11096
  		MAX(REPLACE(cprx.drug_name, '\"', '')) as cprx_drug_name, -- For Order 10497
  		MIN(autofill_yn) as rx_autofill,
  		CAST(MIN(CASE WHEN script_status_cn = 0 AND autofill_resume_date >= @today THEN autofill_resume_date ELSE NULL END) as DATE) as autofill_date,
  		MAX(last_rxdisp_id) as last_rxdisp_id,  --last dispensed fill for this drug
  		CAST(MAX(CASE WHEN script_status_cn = 0 THEN expire_date ELSE NULL END) as DATE) as expire_date, --expiration date of newest script (Juanita F was missing januvia on account details page)
  		MIN(CASE WHEN script_status_cn = 0 AND expire_date > @today AND written_qty * refills_left >=45 THEN script_no ELSE NULL END) as oldest_script_high_refills,  --oldest script that still has refills (does script_status account for rx expiration date?)
  		MIN(CASE WHEN script_status_cn = 0 AND expire_date > @today AND written_qty * refills_left > 0 THEN script_no ELSE NULL END) as oldest_script_with_refills,  --oldest script that still has refills
  		MIN(CASE WHEN script_status_cn = 0 AND expire_date > @today THEN script_no ELSE NULL END) as oldest_active_script,  --Pat_id 2768 had newer Inactive scripts that were hiding her older scripts with 0 refills
  		MAX(script_no) as newest_script  --most recent script that was transferred out, inactivated, or that has no refills
  	FROM cprx
  	LEFT JOIN (
  		SELECT STUFF(MIN(gni+fdrndc.ln), 1, 1, '') as generic_name, fdrndc.gcn_seqno
  		FROM fdrndc
  		GROUP BY fdrndc.gcn_seqno
  	) as generic_name ON generic_name.gcn_seqno = cprx.gcn_seqno
  	--WHERE pat_id = 4293
  	GROUP BY pat_id, RTRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ISNULL(generic_name, cprx.drug_name), ' CAPSULE', ' CAP'),' CAPS',' CAP'),' TABLET',' TAB'),' TABS',' TAB'),' TB', ' TAB'),' HCL',''),' MG','MG'), '\"', '')) --Can't just group by GCN since one drug might have multiple GCNs.  Can't just group by Drug Name since drug name could be brand name or generic name.  Do a FDRNDC lookup to group by generic name when possible
  	HAVING
  	(
  		@today - MAX(expire_date) <= 6*30 AND
  		MIN(ISNULL(cprx.status_cn, 0)) < 2 AND  -- NULL/0 is active, 1 is not yet dispensed?, 2 is transferred out/inactive, 3 is voided
  		SUM(CASE WHEN script_status_cn = 0 THEN refills_left ELSE 0 END) >= .1  -- OR active script with at least some refills remaining

  	) OR
  	(
  		MAX(last_transfer_type_io) = 'O' AND
  		@today - MAX(chg_date) <= 6*30 -- include all recent scripts (6 months) even if out of refills or transferred out

  	)  OR
  	(
  		SUM(CASE WHEN script_status_cn = 0 THEN refills_left ELSE 0 END) < .1 AND
  		@today - MAX(chg_date) <= 6*30 -- include all recent scripts (6 months) even if out of refills or transferred out
  	)"
  );*/
}
