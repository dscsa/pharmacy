<?php
require_once 'changes/changes_to_rxs_single.php';
require_once 'helpers/helper_parse_sig.php';
require_once 'helpers/helper_imports.php';
require_once 'dbs/mysql_wc.php';

use Sirum\Logging\SirumLog;

function update_rxs_single() {

  $mysql = new Mysql_Wc();
  $mssql = new Mssql_Cp();


  /**
   * All RX should have a rx_message set.  We are going to query the database
   * and look for any with a NULL rx_message_key.  If we dint one, get_full_patient()
   * will fetch the user and update the message?
   *
   *  NOTE Using an INNER JOIN to exclude Rxs associated with patients that are inactive or deceased
   */
  $rx_singles = $mysql->run(
    "SELECT *
      FROM gp_rxs_single
        JOIN gp_patients ON gp_patients.patient_id_cp = gp_rxs_single.patient_id_cp
      WHERE rx_message_key IS NULL"
  );

  foreach($rx_singles[0] as $rx_single) {

    SirumLog::$subroutine_id = "rxs-single-null-message-".sha1(serialize($rx_single));

    //This updates & overwrites set_rx_messages
    $patient = get_full_patient($rx_single, $mysql, $rx_single['rx_number']);

    //These should have been given an rx_message upon creation.  Why was it missing?
    SirumLog::error(
      "rx had an empty message, so just set it.  Why was it missing?",
      [
        "patient_id_cp" => $patient[0]['patient_id_cp'],
        "patient_id_wc" => $patient[0]['patient_id_wc'],
        "rx_single"     => $rx_single,
        "source"        => "CarePoint",
        "type"          => "rxs-single",
        "event"         => "null-message"
      ]
    );
  }

  /* Now to do some work */

  $changes = changes_to_rxs_single("gp_rxs_single_cp");

  $count_deleted = count($changes['deleted']);
  $count_created = count($changes['created']);
  $count_updated = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  log_info("update_rxs_single: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  /*
   * Created Loop #1 First loop accross new items. Run this before rx_grouped query to make
   * sure all sig_qty_per_days are properly set before we group by them
   */
  foreach($changes['created'] as $created) {

    SirumLog::$subroutine_id = "rxs-single-created1-".sha1(serialize($created));

    SirumLog::debug(
      "update_rxs_single: rx created1",
      [
          'created' => $created,
          'source'  => 'CarePoint',
          'type'    => 'rxs-single',
          'event'   => 'created1'
      ]
    );

    // Get the signature
    $parsed = get_parsed_sig($created['sig_actual'], $created['drug_name']);

    // If we have more than 8 a day, lets have a human verify the signature
    if ($parsed['qty_per_day'] > 8) {
      $created_date = "Created:".date('Y-m-d H:i:s');
      $salesforce   = [
        "subject"   => "Verify qty pended for $created[drug_name] for Rx #$created[rx_number]",
        "body"      => "For Rx #$created[rx_number], $created[drug_name] with sig '$created[sig_actual]' was parsed as $parsed[qty_per_day] qty per day, which is very high. $created_date",
        "contact"   => "$created[first_name] $created[last_name] $created[birth_date]",
        "assign_to" => ".DDx/Sig Issue - RPh",
        "due_date"  => date('Y-m-d')
      ];

      $event_title = "$item[invoice_number] Sig Parsing Error: $salesforce[contact] $created_date";

      create_event($event_title, [$salesforce]);

      // TODO make this a warning not an error
      log_error(
        $salesforce['body'],
        [
          'salesforce' => $salesforce,
          'created' => $created,
          'parsed' => $parsed
        ]
      );
    }

    //TODO Eventually Save the Clean Script back into Guardian so that Cindy doesn't need to rewrite them
    set_parsed_sig($created['rx_number'], $parsed, $mysql);
  }

  /* Finishe Loop Created Loop  #1 */

  /*
   * This work is to create the perscription groups.
   *
   * This is an expensive (6-8 seconds) group query.
   * TODO We should update rxs in this table individually on changes (AK OR USE THE SAME CHANGE MECHANISM ON THE OTHER TABLES TO DETECT/UPDATE CHANGES)
   * TODO OR We should add indexed drug info fields to the gp_rxs_single above on
   *      created/updated so we don't need the join
   */

  //This Group By Clause must be kept consistent with the grouping with the export_cp_set_rx_message query
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
        WHEN MIN(rx_autofill) > 0 THEN (
          CASE
            WHEN MAX(refill_date_manual) > NOW()
            THEN MAX(refill_date_manual)
            ELSE MAX(refill_date_default)
          END)
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
      NULLIF(MIN(COALESCE(rx_date_transferred, '0')), '0') as rx_date_transferred -- Only mark as transferred if ALL Rxs are transferred out

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

  // QUESTION Do we need to get everthing or would a LIMIT 1 be fine?
  $mysql->run("SELECT * FROM gp_rxs_grouped")[0]
    ? $mysql->commit()
    : $mysql->rollback();


  /*
   * Created Loop #2 We are now assigning the rx group to the new patients
   * from created list.  We ae allso removing any drug refils.
   *
   * QUESTION Do new users have drug refils?
   *
   * Run this After so that Rx_grouped is set when doing get_full_patient
   */
  foreach($changes['created'] as $created) {

    SirumLog::$subroutine_id = "rxs-single-created2-".sha1(serialize($created));

    SirumLog::debug(
      "update_rxs_single: rx created2",
      [
          'created' => $created,
          'source'  => 'CarePoint',
          'type'    => 'rxs-single',
          'event'   => 'created2'
      ]
    );

    // This updates & overwrites set_rx_messages.  TRUE because this one
    // Rx might update many other Rxs for the same drug.
    $patient = get_full_patient($created, $mysql, true);

    remove_drugs_from_refill_reminders(
      $patient[0]['first_name'],
      $patient[0]['last_name'],
      $patient[0]['birth_date'],
      [$created['drug_name']]
    );
  }

  /* Finish Created Loop #2 */


  /*
   * Updated Loop
   */
  //Run this after rx_grouped query to ensure get_full_patient retrieves an accurate order profile
  foreach($changes['updated'] as $updated) {

    SirumLog::$subroutine_id = "rxs-single-updated-".sha1(serialize($updated));

    SirumLog::debug(
      "update_rxs_single: rx updated",
      [
          'updated' => $updated,
          'source'  => 'CarePoint',
          'type'    => 'rxs-single',
          'event'   => 'updated'
      ]
    );

    $changed = changed_fields($updated);
    $cp_id   = $updated['patient_id_cp'];
    $patient = get_full_patient($updated, $mysql, $updated['rx_number']);

    if ($updated['rx_autofill'] != $updated['old_rx_autofill']) {
      $sql     = ""; //Reset for logging

      //We want all Rxs with the same GSN to share the same rx_autofill value, so when one changes we must change them all
      //SQL to DETECT inconsistencies:
      //SELECT patient_id_cp, rx_gsn, MAX(drug_name), MAX(CONCAT(rx_number, rx_autofill)), GROUP_CONCAT(rx_autofill), GROUP_CONCAT(rx_number) FROM gp_rxs_single GROUP BY patient_id_cp, rx_gsn HAVING AVG(rx_autofill) > 0 AND AVG(rx_autofill) < 1
      foreach ($patient as $item) {
        if (strpos($item['rx_numbers'], ",$updated[rx_number],") !== false) {
          $in  = str_replace(',', "','", substr($item['drug_gsns'], 1, -1)); //use drugs_gsns instead of rx_gsn just in case there are multiple gsns for this drug
          $sql = "UPDATE cprx SET autofill_yn = $updated[rx_autofill], chg_date = GETDATE() WHERE pat_id = $cp_id AND gcn_seqno IN ('$in')";
          $mssql->run($sql);
        }
      }

      log_notice("update_rxs_single rx_autofill changed.  Updating all Rx's with same GSN to be on/off Autofill. Confirm correct updated rx_messages", ['patient' => $patient, 'updated' => $updated, 'sql' => $sql, 'changed' => $changed]);
    }

    if ($updated['rx_gsn'] AND ! $updated['old_rx_gsn']) {

      $item = get_full_item($updated, $mysql); //TODO enable this to update and overwite rx_messages so we can avoid call above

      v2_pend_item($item, $mysql);

      //TODO do we need to update the patient, that we are now including this drug if $item['days_dispensed_default'] AND ! $item['rx_dispensed_id']?

      log_error("update_rxs_single rx_gsn no longer missing (but still might not be in v2 yet).  Confirm correct updated rx_messages", [$item, $updated, $changed]);
    }

    if ($updated['rx_transfer'] AND ! $updated['old_rx_transfer']) {
      log_error("update_rxs_single rx was transferred out.  Confirm correct updated rxs_single.rx_message_key. rxs_grouped.rx_message_keys will be updated on next pass", [$patient, $updated, $changed]);
    }

  }

  SirumLog::resetSubroutineId();




  //TODO if new Rx arrives and there is an active order where that Rx is not included because of "ACTION NO REFILLS" or "ACTION RX EXPIRED" or the like, then we should rerun the helper_days_dispensed on the order_item

  //TODO Implement rx_status logic that was in MSSQL Query and Save in Database

  //TODO Maybe? Update Salesforce Objects using REST API or a MYSQL Zapier Integration

  //TODO THIS NEED TO BE UPDATED TO MYSQL AND TO INCREMENTAL BASED ON CHANGES

  //TODO Add Group by "Qty per Day" so its GROUP BY Pat Id, Drug Name,

  //TODO GCN Updates Here Should Update Any Order Item That is has not GCN match

}
