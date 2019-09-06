<?php
require_once 'changes/changes_to_rxs_single.php';
require_once 'helpers/helper_parse_sig.php';
require_once 'helpers/helper_imports.php';
require_once 'dbs/mysql_wc.php';

function update_rxs_single() {

  $changes = changes_to_rxs_single("gp_rxs_single_cp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  $message = "
  update_rxs_single: $count_deleted deleted, $count_created created, $count_updated updated. ";

  echo $message;

  mail('adam@sirum.org', "CRON: $message", $message.print_r($changes, true));

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  $mysql = new Mysql_Wc();

  foreach($changes['created'] as $rx) {

    $sig = parse_sig($rx);

    if ($sig)
      $mysql->run("
        UPDATE gp_rxs_single SET
          sig_clean = $sig[sig_clean],
          sig_qty_per_day = $sig[sig_qty_per_day],
          sig_qty_per_time = $sig[sig_qty_per_time],
          sig_frequency = $sig[sig_frequency],
          sig_frequency_numerator = $sig[sig_frequency_numerator],
          sig_frequency_denominator = $sig[sig_frequency_denominator]
        WHERE
          rx_number = $rx[rx_number]
      ");
  }

  //This is an expensive (6-8 seconds) group query.
  //TODO We should update rxs in this table individually on changes
  //TODO OR We should add indexed drug info fields to the gp_rxs_single above on created/updated so we don't need the join
  $mysql->run('TRUNCATE TABLE gp_rxs_grouped');

  $mysql->run("
    INSERT INTO gp_rxs_grouped
    SELECT
  	  patient_id_cp,
      COALESCE(drug_generic, drug_name),
      MAX(drug_brand) as drug_brand,
      MAX(drug_name) as drug_name,
      sig_qty_per_day,

      MAX(rx_gsn) as max_gsn,
      MAX(drug_gsns) as drug_gsns,
      SUM(refills_left) as refills_total,
      MIN(rx_autofill) as rx_autofill,

      MIN(refill_date_first) as refill_date_first,
      MAX(refill_date_last) as refill_date_last,
      CASE
        WHEN MIN(rx_autofill) > 0 THEN COALESCE(MAX(refill_date_manual), MAX(refill_date_default))
        ELSE NULL
      END as refill_date_next,
      MAX(refill_date_manual) as refill_date_manual,
      MAX(refill_date_default) as refill_date_default,
      NULL as refill_date_target,
      NULL as refill_target_days,
      NULL as refill_target_count,

      COALESCE(
        MIN(CASE WHEN qty_left >= 45 AND days_left >= 45 THEN rx_number ELSE NULL END),
        MIN(CASE WHEN qty_left >= 0 THEN rx_number ELSE NULL END),
        MIN(CASE WHEN rx_status = 0 AND days_left >= 0 THEN rx_number ELSE NULL END),
    	  MAX(rx_number)
      ) as best_rx_number,

      CONCAT(',', GROUP_CONCAT(rx_number), ',') as rx_numbers,

      MAX(rx_date_changed) as rx_date_changed,
      MAX(rx_date_expired) as rx_date_expired
  	FROM gp_rxs_single
  	LEFT JOIN gp_drugs ON
      drug_gsns LIKE CONCAT('%,', rx_gsn, ',%')
  	GROUP BY
      patient_id_cp,
      COALESCE(drug_generic, drug_name),
      sig_qty_per_day
  ");

  /*
  COALSECE(
    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN qty_left >= 45 AND days_left >= 45 THEN days_left ELSE NULL END ORDER BY rx_number ASC), ',', 1),
    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN qty_left >= 0 THEN days_left ELSE NULL END ORDER BY rx_number ASC), ',', 1),
    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN rx_status = 0 AND days_left >= 0 THEN days_left ELSE NULL END ORDER BY rx_number ASC), ',', 1),
    SUBSTRING_INDEX(GROUP_CONCAT(days_left ORDER BY rx_number DESC), ',', 1)
  ) as best_days_left,

  COALSECE(
    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN qty_left >= 45 AND days_left >= 45 THEN rx_number ELSE NULL END ORDER BY rx_number ASC), ',', 1),
    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN qty_left >= 0 THEN rx_number ELSE NULL END ORDER BY rx_number ASC), ',', 1),
    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN rx_status = 0 AND days_left >= 0 THEN rx_number ELSE NULL END ORDER BY rx_number ASC), ',', 1),
    SUBSTRING_INDEX(GROUP_CONCAT(rx_number ORDER BY rx_number DESC), ',', 1)
  ) as best_qty_left,
  */

  //DRUG SYNCING.
  // 1) FOR EACH REFILL DATE FOR A PATIENT, FIND OUT HOW MANY DRUGS WILL BE ON IT AND PRIORTIZE BY THAT, BREAKING TIES WITH LONGER DATE
  // 2) COMPARE EACH OF THESE "TARGET DATES" WITH OUR NEXT FILL DATE.  IT MUST BE 30<=Target<=90 or 100<=Target<=120 TO BE AN ELIGIBLE MATCH.
  //    NOTE: ANYTHING THAT 80<TARGET<100 doesn't need to be synced since that's the default
  // 3) UPDATE OUR GROUPED TABLE WITH REFILL_DATE_TARGET, REFILL_TARGET_DATE, & REFILL_TARGET_COUNT with any matches
  $mysql->run("
    UPDATE gp_rxs_grouped
    LEFT JOIN (
      SELECT
        gp_rxs_grouped.patient_id_cp,
        gp_rxs_grouped.refill_date_next,
        SUBSTRING_INDEX(GROUP_CONCAT(sync_dates.refill_date_next), ',', 1) as refill_date_target,     -- Hacky way to get FIRST()
        SUBSTRING_INDEX(GROUP_CONCAT(sync_dates.refill_target_count), ',', 1) as refill_target_count, -- Hacky way to get FIRST()
        (SELECT
          COUNT(*)
          FROM gp_rxs_grouped as sub
          WHERE
            gp_rxs_grouped.patient_id_cp    = sub.patient_id_cp AND
            gp_rxs_grouped.refill_date_next = sub.refill_date_next
        ) as from_count
      FROM (
        SELECT
          patient_id_cp,
          refill_date_next,
          COUNT(*) as refill_target_count
        FROM
          gp_rxs_grouped
        GROUP BY
          patient_id_cp,
          refill_date_next
        ORDER BY
          patient_id_cp,
          COUNT(*) DESC,
          refill_date_next DESC
      ) sync_dates
      JOIN gp_rxs_grouped ON
        gp_rxs_grouped.patient_id_cp = sync_dates.patient_id_cp AND
        DATEDIFF(sync_dates.refill_date_next, gp_rxs_grouped.refill_date_next)  >= 30 AND
        DATEDIFF(sync_dates.refill_date_next, gp_rxs_grouped.refill_date_next)  <= 120 AND
        (DATEDIFF(sync_dates.refill_date_next, gp_rxs_grouped.refill_date_next) <= 80 OR DATEDIFF(sync_dates.refill_date_next, gp_rxs_grouped.refill_date_next) >= 100)
      GROUP BY
        gp_rxs_grouped.patient_id_cp,
        gp_rxs_grouped.refill_date_next
    ) sync_dates ON
      gp_rxs_grouped.patient_id_cp    = sync_dates.patient_id_cp AND
      gp_rxs_grouped.refill_date_next = sync_dates.refill_date_next AND
      sync_dates.refill_target_count   >= sync_dates.from_count

    SET
      gp_rxs_grouped.refill_date_target  = sync_dates.refill_date_target,
      gp_rxs_grouped.refill_target_count = sync_dates.refill_target_count,
      gp_rxs_grouped.refill_target_days  = DATEDIFF(sync_dates.refill_date_target, sync_dates.refill_date_next)
  ");

  //TODO Calculate Qty Per Day from Sig and save in database

  //TODO Implement rx_status logic that was in MSSQL Query and Save in Database

  //TODO Maybe? Update Salesforce Objects using REST API or a MYSQL Zapier Integration

  //TODO THIS NEED TO BE UPDATED TO MYSQL AND TO INCREMENTAL BASED ON CHANGES

  //TODO Add Group by "Qty per Day" so its GROUP BY Pat Id, Drug Name,
  /*

    SELECT
  		patient_id_cp,
      drug_name,
      sig_qty_per_day,

      MAX(gcn) as gcn,
      SUM(refills_left) as refills_total,
      MIN(rx_autofill) as rx_autofill,
      MAX(rx_date_changed) as rx_date_changed,
      MAX(rx_date_expired) as rx_date_expired,

      MIN(refill_date_first) as refill_date_first,
      MAX(refill_date_last) as refill_date_last,
      COALESCE(MIN(refill_date_manual), MIN(refill_date_default)) as refill_date_next,
      MIN(refill_date_manual) as refill_date_manual,
      MIN(refill_date_default) as refill_date_default,

      MIN(CASE WHEN qty_left >= 45 AND days_left >= 45 THEN rx_number ELSE NULL END) as oldest_rx_high_refills,
		  MIN(CASE WHEN qty_left >= 0 THEN rx_number ELSE NULL END) as oldest_script_with_refills,
		  MIN(CASE WHEN rx_status = 0 AND days_left >= 0 THEN rx_number ELSE NULL END) as oldest_active_script,
  		MAX(rx_number) as newest_script
  	FROM gp_rxs_single
  	GROUP BY
    	patient_id_cp,
      drug_name,
      sig_qty_per_day

  */
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
