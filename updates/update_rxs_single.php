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

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_rxs_single: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();

  //Created Loop #1 of 2: Run this before rx_grouped query to make sure all sig_qty_per_days are probably set before we group by them
  foreach($changes['created'] as $created) {

    $parsed = get_parsed_sig($created['sig_actual'], $created['drug_name']);

    //TODO Eventually Save the Clean Script back into Guardian so that Cindy doesn't need to rewrite them
    set_parsed_sig($created['rx_number'], $parsed, $mysql);
  }

  //This is an expensive (6-8 seconds) group query.
  //TODO We should update rxs in this table individually on changes
  //TODO OR We should add indexed drug info fields to the gp_rxs_single above on created/updated so we don't need the join

  $sql = "
    INSERT INTO gp_rxs_grouped
    SELECT
  	  patient_id_cp,
      COALESCE(drug_generic, drug_name),
      MAX(drug_brand) as drug_brand,
      MAX(drug_name) as drug_name,
      COALESCE(sig_qty_per_day_actual, sig_qty_per_day_default) as sig_qty_per_day,
      GROUP_CONCAT(DISTINCT rx_message_key) as rx_message_keys,

      MAX(rx_gsn) as max_gsn,
      MAX(drug_gsns) as drug_gsns,
      SUM(refills_left) as refills_total,
      SUM(qty_left) as qty_total,
      MIN(rx_autofill) as rx_autofill, -- if one is taken off, then a new script will have it turned on but we need to go with the old one

      MIN(refill_date_first) as refill_date_first,
      MAX(refill_date_last) as refill_date_last,
      CASE
        WHEN MIN(rx_autofill) > 0 THEN COALESCE(MAX(refill_date_manual), MAX(refill_date_default))
        ELSE NULL
      END as refill_date_next,
      MAX(refill_date_manual) as refill_date_manual,
      MAX(refill_date_default) as refill_date_default,

      COALESCE(
        MIN(CASE WHEN qty_left >= ".DAYS_MIN." AND days_left >= ".DAYS_MIN." THEN rx_number ELSE NULL END),
        MIN(CASE WHEN qty_left > 0 AND days_left > 0 THEN rx_number ELSE NULL END),
    	  MAX(rx_number)
      ) as best_rx_number,

      CONCAT(',', GROUP_CONCAT(rx_number), ',') as rx_numbers,

      CASE
        WHEN MAX(refill_date_first) IS NULL THEN GROUP_CONCAT(DISTINCT rx_source)
        ELSE 'Refill'
      END as rx_sources,

      MAX(rx_date_changed) as rx_date_changed,
      MAX(rx_date_expired) as rx_date_expired,
      NULLIF(MIN(CASE WHEN rx_transfer = 'O' THEN rx_date_changed ELSE 0 END), 0) as rx_date_transferred -- Only mark as transferred if ALL Rxs are transferred out

  	FROM gp_rxs_single
  	LEFT JOIN gp_drugs ON
      drug_gsns LIKE CONCAT('%,', rx_gsn, ',%')
  	GROUP BY
      patient_id_cp,
      COALESCE(drug_generic, drug_name),
      COALESCE(sig_qty_per_day_actual, sig_qty_per_day_default)
  ";


  $mysql->transaction();
  $mysql->run("DELETE FROM gp_rxs_grouped");
  $mysql->run($sql);
  $mysql->run("SELECT * FROM gp_rxs_grouped")[0]
    ? $mysql->commit()
    : $mysql->rollback();

  //Created Lopp #2 of 2: Run this After so that Rx_grouped is set when doing get_full_patient
  foreach($changes['created'] as $created) {

    $patient = get_full_patient($created, $mysql, $created['rx_number']); //This updates & overwrites set_rx_messages

    remove_drugs_from_refill_reminders($patient['first_name'], $patient['last_name'], $patient['birth_date'], [$created['drug_name']]);
  }

  //Run this after rx_grouped query to ensure get_full_patient retrieves an accurate order profile
  foreach($changes['updated'] as $updated) {

    $changed = changed_fields($updated);

    if ($updated['rx_autofill'] != $updated['old_rx_autofill']) {

      $patient = @$patient ?: get_full_patient($updated, $mysql, $updated['rx_number']);
      $sql     = ""; //Reset for logging

      //We want all Rxs with the same GSN to share the same rx_autofill value, so when one changes we must change them all
      //SQL to DETECT inconsistencies:
      //SELECT patient_id_cp, rx_gsn, MAX(drug_name), MAX(CONCAT(rx_number, rx_autofill)), GROUP_CONCAT(rx_autofill), GROUP_CONCAT(rx_number) FROM gp_rxs_single GROUP BY patient_id_cp, rx_gsn HAVING AVG(rx_autofill) > 0 AND AVG(rx_autofill) < 1
      foreach ($patient as $item) {
        if (strpos($item['rx_numbers'], ",$updated[rx_number],") !== false) {
          $in  = str_replace(',', "','", substr($item['drug_gsns'], 1, -1)); //use drugs_gsns instead of rx_gsn just in case there are multiple gsns for this drug
          $sql = "UPDATE cprx SET autofill_yn = $updated[rx_autofill], chg_date = GETDATE() WHERE pat_id = $updated[patient_id_cp] AND gcn_seqno IN ('$in')";
          $mssql->run($sql);
        }
      }

      log_notice("update_rxs_single rx_autofill changed.  Updating all Rx's with same GSN to be on/off Autofill. Confirm correct updated rx_messages", ['patient' => $patient, 'updated' => $updated, 'sql' => $sql, 'changed' => $changed]);
    }

    if ($updated['rx_gsn'] AND ! $updated['old_rx_gsn']) {

      $item = get_full_item($updated, $mysql); //This updates & overwrites set_rx_messages

      v2_pend_item($item, $mysql);

      log_error("update_rxs_single rx_gsn no longer missing (but still might not be in v2 yet).  Confirm correct updated rx_messages", [$item, $updated, $changed]);
    }

    if ($updated['rx_transfer'] AND ! $updated['old_rx_transfer']) {

      $patient = @$patient ?: get_full_patient($updated, $mysql, $updated['rx_number']); //This updates & overwrites set_rx_messages

      log_error("update_rxs_single rx was transferred out.  Confirm correct updated rxs_single.rx_message_key. rxs_grouped.rx_message_keys will be updated on next pass", [$patient, $updated, $changed]);
    }

  }

  //TODO if new Rx arrives and there is an active order where that Rx is not included because of "ACTION NO REFILLS" or "ACTION RX EXPIRED" or the like, then we should rerun the helper_days_dispensed on the order_item

  //TODO Implement rx_status logic that was in MSSQL Query and Save in Database

  //TODO Maybe? Update Salesforce Objects using REST API or a MYSQL Zapier Integration

  //TODO THIS NEED TO BE UPDATED TO MYSQL AND TO INCREMENTAL BASED ON CHANGES

  //TODO Add Group by "Qty per Day" so its GROUP BY Pat Id, Drug Name,

  //TODO GCN Updates Here Should Update Any Order Item That is has not GCN match
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
