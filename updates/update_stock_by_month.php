<?php
require_once 'changes/changes_to_stock_by_month.php';
require_once 'dbs/mysql_wc.php';

function update_stock_by_month() {

  $changes = changes_to_stock_by_month("gp_stock_by_month_v2");

  $month_interval = 4;
  $count_deleted  = count($changes['deleted']);
  $count_created  = count($changes['created']);
  $count_updated  = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  //log_info("update_stock_by_month: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();

  $sql1 = "
    INSERT INTO gp_stock_live
    SELECT

      * ,

      IF (
        drug_ordered IS NULL,
        IF(zscore > zhigh_threshold, 'ORDER DRUG', 'NOT OFFERED'),
        IF (
          -- total_dispensed_actual can be more than inventory because it is the sum over a 4 month period
         total_dispensed_default > last_inventory,
          -- Drugs that are recently ordered and never dispensed should not be labeled out of stock
          IF(total_dispensed_actual > 0, 'OUT OF STOCK', 'LOW SUPPLY'),
          IF(
            zlow_threshold IS NULL OR zhigh_threshold IS NULL,
            'PRICE ERROR',
            IF(
              zscore < zlow_threshold,
              IF(total_dispensed_actual > last_inventory/10 OR last_inventory < 1000, 'REFILL ONLY', 'ONE TIME'),
              IF(
                zscore < zhigh_threshold,
              	'LOW SUPPLY',
              	'HIGH SUPPLY'
              )
            )
          )
        )
      ) as stock_level

      FROM (
        SELECT

        *,

        -- zscore >= 0.25 -- 60% Confidence will be in stock for this 4 month interval.  Since we have inventory for previous 4 months we think the chance of stock out is about 40%^2
        -- zscore >= 0.52 -- 70% Confidence will be in stock for this 4 month interval.  Since we have inventory for previous 4 months we think the chance of stock out is about 30%^2
        -- zscore >= 1.04 -- 85% Confidence will be in stock for this 4 month interval.  Since we have inventory for previous 4 months we think the chance of stock out is about 15%^2
        -- zscore >= 1.64 -- 95% Confidence will be in stock for this 4 month interval.  Since we have inventory for previous 4 months we think the chance of stock out is about 5%^2
        -- zscore >= 2.05 -- 98% Confidence will be in stock for this 4 month interval.  Since we have inventory for previous 4 months we think the chance of stock out is about 2%^2
        -- zscore >= 2.33 -- 99% Confidence will be in stock for this 4 month interval.  Since we have inventory for previous 4 months we think the chance of stock out is about 1%^2
        IF(price_per_month = 20, 2.05, IF(price_per_month = 8, 1.04, IF(price_per_month = 2, 0.25, NULL))) as zlow_threshold,
        IF(price_per_month = 20, 2.33, IF(price_per_month = 8, 1.64, IF(price_per_month = 2, 0.52, NULL))) as zhigh_threshold,

        -- https://www.dummies.com/education/math/statistics/creating-a-confidence-interval-for-the-difference-of-two-means-with-known-standard-deviations/
        (total_entered/$month_interval - COALESCE(total_dispensed_actual, total_dispensed_default)/$month_interval)/POWER(POWER(stddev_entered, 2)/$month_interval+POWER(COALESCE(stddev_dispensed_actual, stddev_dispensed_default), 2)/$month_interval, .5) as zscore

        FROM (
          SELECT
           gp_stock_by_month.drug_generic,
           MAX(gp_drugs.drug_brand) as drug_brand,
           MAX(gp_drugs.drug_gsns) as drug_gsns,
           MAX(gp_drugs.message_display) as message_display,

           MAX(COALESCE(price30, price90/3)) as price_per_month,
           MAX(gp_drugs.drug_ordered) as drug_ordered,
           MAX(gp_drugs.qty_repack) as qty_repack,

           GROUP_CONCAT(CONCAT(month, ' ', inventory_sum) ORDER BY month ASC) as months_inventory,
           AVG(inventory_sum) as avg_inventory,
           SUBSTRING_INDEX(GROUP_CONCAT(inventory_sum ORDER BY month ASC), ',', -1) as last_inventory,

           GROUP_CONCAT(CONCAT(month, ' ', entered_sum) ORDER BY month ASC) as months_entered,
           -- Exclude current (partial) month from STD_DEV as it will look very different from full months
           STDDEV_SAMP(IF(month <= (CURDATE() - INTERVAL 1 MONTH), entered_sum, NULL)) as stddev_entered,
           SUM(entered_sum) as total_entered,

           GROUP_CONCAT(CONCAT(month, ' ', dispensed_sum) ORDER BY month ASC) as months_dispensed,
           -- Exclude current (partial) month from STD_DEV as it will look very different from full months
           IF(STDDEV_SAMP(IF(month <= (CURDATE() - INTERVAL 1 MONTH), dispensed_sum, NULL)) > 0, STDDEV_SAMP(IF(month <= (CURDATE() - INTERVAL 1 MONTH), dispensed_sum, NULL)), NULL) as stddev_dispensed_actual,
           IF(SUM(dispensed_sum) > 0, SUM(dispensed_sum), NULL) as total_dispensed_actual,

           2*COALESCE(MAX(gp_drugs.qty_repack), 135) as total_dispensed_default,
           2*COALESCE(MAX(gp_drugs.qty_repack), 135)/POWER($month_interval, .5) as stddev_dispensed_default

           FROM
            gp_stock_by_month
           JOIN gp_drugs ON
            gp_drugs.drug_generic = gp_stock_by_month.drug_generic

           WHERE
            month > (CURDATE() - INTERVAL ".($month_interval+1)." MONTH) AND
            month <= (CURDATE() - INTERVAL 0 MONTH)
           GROUP BY
            gp_stock_by_month.drug_generic
        ) as subsub
     ) as sub
  ";

  $sql2 = "
    UPDATE
      gp_stock_by_month
    JOIN
      gp_stock_live ON gp_stock_live.drug_generic = gp_stock_by_month.drug_generic
    SET
      gp_stock_by_month.drug_brand = gp_stock_live.drug_brand,
      gp_stock_by_month.drug_gsns = gp_stock_live.drug_gsns,
      gp_stock_by_month.message_display = gp_stock_live.message_display,
      gp_stock_by_month.price_per_month = gp_stock_live.price_per_month,
      gp_stock_by_month.drug_ordered = gp_stock_live.drug_ordered,
      gp_stock_by_month.qty_repack = gp_stock_live.qty_repack,
      gp_stock_by_month.months_inventory = gp_stock_live.months_inventory,
      gp_stock_by_month.avg_inventory = gp_stock_live.avg_inventory,
      gp_stock_by_month.last_inventory = gp_stock_live.last_inventory,
      gp_stock_by_month.months_entered = gp_stock_live.months_entered,
      gp_stock_by_month.stddev_entered = gp_stock_live.stddev_entered,
      gp_stock_by_month.total_entered = gp_stock_live.total_entered,
      gp_stock_by_month.months_dispensed = gp_stock_live.months_dispensed,
      gp_stock_by_month.stddev_dispensed_actual = gp_stock_live.stddev_dispensed_actual,
      gp_stock_by_month.total_dispensed_actual = gp_stock_live.total_dispensed_actual,
      gp_stock_by_month.total_dispensed_default = gp_stock_live.total_dispensed_default,
      gp_stock_by_month.stddev_dispensed_default = gp_stock_live.stddev_dispensed_default,
      gp_stock_by_month.zlow_threshold = gp_stock_live.zlow_threshold,
      gp_stock_by_month.zhigh_threshold = gp_stock_live.zhigh_threshold,
      gp_stock_by_month.zscore = gp_stock_live.zscore,
      gp_stock_by_month.stock_level = gp_stock_live.stock_level
    WHERE
      month > (CURDATE() - INTERVAL 1 MONTH)
  ";

  $mysql->run("START TRANSACTION");
  $mysql->run("DELETE FROM gp_stock_live");
  $mysql->run($sql1);
  $mysql->run($sql2);
  $mysql->run("COMMIT");

  $duplicate_gsns = $mysql->run("
    SELECT stock1.drug_generic, stock2.drug_generic, stock1.drug_gsns, stock2.drug_gsns FROM gp_stock_live stock1 JOIN gp_stock_live stock2 ON stock1.drug_gsns LIKE CONCAT('%', stock2.drug_gsns, '%') WHERE stock1.drug_generic != stock2.drug_generic
  ");

  if (isset($duplicate_gsns[0][0])) {
    log_error('Duplicate GSNs in V2', $duplicate_gsns[0]);
  }


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
