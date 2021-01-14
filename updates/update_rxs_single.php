<?php
require_once 'helpers/helper_parse_sig.php';
require_once 'helpers/helper_imports.php';
require_once 'helpers/helper_identifiers.php';
require_once 'exports/export_cp_rxs.php';
require_once 'exports/export_gd_transfer_fax.php'; //is_will_transfer()
require_once 'dbs/mysql_wc.php';

use Sirum\Logging\SirumLog;
use Sirum\Logging\AuditLog;

function update_rxs_single($changes)
{

    $start = microtime(true);
    $mysql = new Mysql_Wc();
    $mssql = new Mssql_Cp();

    /* Now to do some work */
    $count_deleted = count($changes['deleted']);
    $count_created = count($changes['created']);
    $count_updated = count($changes['updated']);

    $msg = "$count_deleted deleted, $count_created created, $count_updated updated ";
    echo $msg;

    SirumLog::info(
        "update_rxs_single: all changes. {$msg}",
        [
            'deleted_count' => $count_deleted,
            'created_count' => $count_created,
            'updated_count' => $count_updated
        ]
    );

    if ( ! $count_deleted and ! $count_created and ! $count_updated) {
      return;
    }

    /*
     * Created Loop #1 First loop accross new items. Run this before rx_grouped query to make
     * sure all sig_qty_per_days are properly set before we group by them
     */
    $loop_timer = microtime(true);
    foreach ($changes['created'] as $created) {

        SirumLog::$subroutine_id = "rxs-single-created1-".sha1(serialize($created));

        $patient = getPatientByRx($created['rx_number']);

        AuditLog::log(
            sprintf(
                "New Rx# %s for %s created via carepoint",
                $created['rx_number'],
                $created['drug_name']
            ),
            $patient
        );

        SirumLog::debug(
            "update_rxs_single: rx created1. Set denormalized data needed for the rxs_grouped table",
            [
                  'created' => $created,
                  'source'  => 'CarePoint',
                  'type'    => 'rxs-single',
                  'event'   => 'created1'
              ]
        );

        log_alert("update_rxs_single: rx created1. did the drug_generic/brand/gsns get added to rxs_single?", [
          'created'  => $created
        ]);

        if ($created['rx_gsn'] AND is_gsn_in_v2($mysql, $created['rx_number'])) {
            //compliment method, update_order_item_drug, doesn't need to be called because order_item will be new and won't need to be updated
            update_rx_single_drug($mysql, $created['rx_number']);

        } else {

            $created_date = "Created:".date('Y-m-d H:i:s');

            if ($created['rx_gsn']) {
                $subject = "NEW {$created['rx_number']} still needs GSN {$created['rx_gsn']} added to V2";
                $body    = "{$created['drug_name']} for $subject";
                $assign  = "Joseph";
                log_warning($body, $created);

            } else {
                $subject = "NEW {$created['rx_number']} still needs to be switched to a drug with a GSN";
                $body    = "{$created['drug_name']} for $subject in CarePoint";
                $assign  = ".Delay/Expedite Order - RPh";
                log_notice($body, $created);
            }

            $salesforce = [
              "subject"   => $subject,
              "body"      => "$body $created_date",
              "contact"   => "{$patient['first_name']} {$patient['last_name']} {$patient['birth_date']}",
              "assign_to" => $assign,
              "due_date"  => date('Y-m-d')
            ];

            $event_title = @$created['rx_number']." Missing GSN: {$salesforce['contact']} $created_date";
        }

        // Get the signature
        $parsed = get_parsed_sig($created['sig_actual'], $created['drug_name']);

        // If we have more than 8 a day, lets have a human verify the signature
        if ($parsed['qty_per_day'] > MAX_QTY_PER_DAY) {
            $created_date = "Created:".date('Y-m-d H:i:s');
            $salesforce   = [
                "subject"   => "Verify qty pended for $created[drug_name] for Rx #$created[rx_number]",
                "body"      => "For Rx #$created[rx_number], $created[drug_name] with sig '$created[sig_actual]' was parsed as $parsed[qty_per_day] qty per day, which is very high. $created_date",
                "contact"   => "$patient[first_name] $patient[last_name] $patient[birth_date]",
                "assign_to" => ".DDx/Sig Issue - RPh",
                "due_date"  => date('Y-m-d')
            ];

            $event_title = "$item[invoice_number] Sig Parsing Error: $salesforce[contact] $created_date";

            create_event($event_title, [$salesforce]);

            SirumLog::warning(
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
    log_timer('rx-singles-created1', $loop_timer, $count_created);


    /* Finish Loop Created Loop  #1 */


    $loop_timer = microtime(true);

    foreach ($changes['updated'] as $updated) {
      SirumLog::$subroutine_id = "rxs-single-updated1-".sha1(serialize($updated));

      $changed = changed_fields($updated);

      $patient = getPatientByRx($created['rx_number']);

      SirumLog::debug(
          "update_rxs_single1: rx updated $updated[drug_name] $updated[rx_number]",
          [
              'updated' => $updated,
              'changed' => $changed,
              'patient' => $patient,
              'source'  => 'CarePoint',
              'type'    => 'rxs-single',
              'event'   => 'updated1'
          ]
      );

      if ($updated['refill_date_first'] AND ! $updated['rx_gsn']) {
        log_warning("RX is missing GSN but refill RXs cannot be changed", $updated);
      }

      if ($updated['rx_gsn'] != $updated['old_rx_gsn']) {

        SirumLog::warning(
          "update_rxs_single1 rx_gsn updated (before rxs_grouped)",
          [
            'updated' => $updated,
            'changed' => $changed
          ]
        );

        if ($updated['rx_gsn'] AND is_gsn_in_v2($mysql, $updated['rx_number'])) {
            //compliment method, update_order_item_drug, doesn't need to be called because order_item will be new and won't need to be updated
            update_rx_single_drug($mysql, $updated['rx_number']);

        } else {

            $created_date = "Created:".date('Y-m-d H:i:s');

            if ($updated['rx_gsn']) {
                $subject = "UPDATED {$updated['rx_number']} still needs GSN {$updated['rx_gsn']} added to V2";
                $body    = "{$updated['drug_name']} for $subject";
                $assign  = "Joseph";
                log_warning($body, $updated);

            } else {
                $subject = "UPDATED {$updated['rx_number']} still needs to be switched to a drug with a GSN";
                $body    = "{$updated['drug_name']} for $subject in CarePoint";
                $assign  = ".Delay/Expedite Order - RPh";
                log_notice($body, $updated);
            }

            $salesforce = [
              "subject"   => $subject,
              "body"      => "$body $created_date",
              "contact"   => "{$patient['first_name']} {$patient['last_name']} {$patient['birth_date']}",
              "assign_to" => $assign,
              "due_date"  => date('Y-m-d')
            ];

            $event_title = @$updated['rx_number']." Missing GSN: {$salesforce['contact']} $created_date";
        }
      }
    }
    log_timer('rx-singles-updated1', $loop_timer, $count_updated);

    /*
     * This work is to create the perscription groups.
     *
     * This is an expensive (~30 seconds) group query.
     * TODO We should update rxs in this table individually on changes (
     * TODO AK: ABOVE CHANGE WOULD ENABLE US TO HAVE AUTOINCREMENT IDS FOR RX_GROUPED - WHICH WE SAVE BACK INTO RXS_SINGLE or a LOOKUP TABLE - THIS WILL HELP QUERY SPEED BY REPLACE LIKE %% AND FIND_IN_SET)
     * TODO AND/OR We should add indexed drug info fields to the gp_rxs_single above on
     *      created/updated so we don't need the wildcard join which is the slowest part
     */

    //TODO if we make this incremental updates, we need to think about the fields with NOW(), this doesn't easily translate
    //into an created/update/deleted type of update.

    //NOTE This Group By Clause must be kept consistent with the grouping with the export_cp_set_rx_message query
    $sql = "
    INSERT INTO gp_rxs_grouped
    SELECT
  	  patient_id_cp,
      COALESCE(drug_generic, drug_name) as drug_generic,
      MAX(drug_brand) as drug_brand,
      MAX(drug_name) as drug_name,
      COALESCE(sig_qty_per_day_actual, sig_qty_per_day_default) as sig_qty_per_day,
      GROUP_CONCAT(DISTINCT rx_message_key) as rx_message_keys,

      MAX(rx_gsn) as max_gsn, -- this is guardian-supplied rx field
      MAX(drug_gsns) as drug_gsns, -- this is a v2-supplied comma-delimited list of gsns for a drug

      SUM(CASE
        WHEN rx_date_expired > NOW() -- expiring does not trigger an update in the rxs_single page current so we have to watch the field here
        THEN refills_left
        ELSE 0
      END) as refills_total,

      SUM(CASE
        WHEN rx_date_expired > NOW() -- expiring does not trigger an update in the rxs_single page current so we have to watch the field here
        THEN qty_left
        ELSE 0
      END) as qty_total,

      MIN(rx_autofill) as rx_autofill, -- if one is taken off, then a new script will have it turned on but we need to go with the old one

      MIN(refill_date_first) as refill_date_first,
      MAX(refill_date_last) as refill_date_last,

      MIN(CASE
          WHEN refill_date_manual > NOW() -- expiring does not trigger an update in the rxs_single page current so we have to watch the field here
          THEN refill_date_manual
          WHEN refill_date_default > NOW() AND rx_autofill > 0
          THEN refill_date_default
          ELSE NULL
      END) as refill_date_next,

      MIN(CASE -- Max/Min here shouldn't make a difference since they should all be the same
        WHEN refill_date_manual > NOW() -- expiring does not trigger an update in the rxs_single page current so we have to watch the field here
        THEN refill_date_manual
        ELSE NULL
      END) as refill_date_manual,

      MAX(refill_date_default) as refill_date_default,

      COALESCE(
        MIN(CASE WHEN qty_left >= ".DAYS_MIN." AND rx_date_expired >= NOW() + INTERVAL ".DAYS_MIN." DAY THEN rx_number ELSE NULL END),
        MIN(CASE WHEN qty_left > 0 AND rx_date_expired >= NOW() THEN rx_number ELSE NULL END),
    	  MAX(rx_number)
      ) as best_rx_number,

      CONCAT(',', GROUP_CONCAT(rx_number), ',') as rx_numbers,

      GROUP_CONCAT(DISTINCT rx_source) as rx_sources,

      MAX(rx_date_changed) as rx_date_changed,
      MAX(rx_date_expired) as rx_date_expired,
      NULLIF(MIN(COALESCE(rx_date_transferred, '0')), '0') as rx_date_transferred -- Only mark as transferred if ALL Rxs are transferred out

  	FROM gp_rxs_single

  	GROUP BY
      patient_id_cp,
      COALESCE(drug_generic, drug_name),
      COALESCE(sig_qty_per_day_actual, sig_qty_per_day_default)
  ";

    $mysql->transaction();
    $mysql->run("DELETE FROM gp_rxs_grouped");

    $group_timer = microtime(true);
    $mysql->run($sql);
    log_timer('rx-singles-grouped', $group_timer, 1);

    // QUESTION Do we need to get everthing or would a LIMIT 1 be fine?
    $mysql->run("SELECT * FROM gp_rxs_grouped")[0]
    ? $mysql->commit()
    : $mysql->rollback();

    //TODO should we put a UNIQUE contstaint on the rxs_grouped table for bestrx_number and rx_numbers, so that it fails hard
    $duplicate_gsns = $mysql->run("
      SELECT
        best_rx_number,
        GROUP_CONCAT(drug_generic),
        GROUP_CONCAT(drug_gsns),
        COUNT(rx_numbers)
      FROM `gp_rxs_grouped`
      GROUP BY best_rx_number
      HAVING COUNT(rx_numbers) > 1
    ")[0];

    if (isset($duplicate_gsns[0][0])) {
      SirumLog::alert(
        "update_rxs_single: duplicate gsns detected",
        ['duplicate_gsns' => $duplicate_gsns]
      );
    }

    /*
     * Created Loop #2 We are now assigning the rx group to the new patients
     * from created list.  We ae allso removing any drug refils.
     *
     * QUESTION Do new users have drug refils?
     *
     * Run this After so that Rx_grouped is set when doing get_full_patient
     */
    $loop_timer = microtime(true);
    foreach ($changes['created'] as $created) {
        SirumLog::$subroutine_id = "rxs-single-created2-".sha1(serialize($created));

        $patient = getPatientByRx($created['rx_number']);

        SirumLog::debug(
            "update_rxs_single: rx created2",
            [
                  'created' => $created,
                  'patient' => $patient,
                  'source'  => 'CarePoint',
                  'type'    => 'rxs-single',
                  'event'   => 'created2'
            ]
        );

        //TODO rather hackily editing calendar events, probably better to just delete and then recreate them
        remove_drugs_from_refill_reminders(
            $patient['first_name'],
            $patient['last_name'],
            $patient['birth_date'],
            [$created['drug_name']]
        );
    }
    log_timer('rx-singles-created2', $loop_timer, $count_created);

    /* Finish Created Loop #2 */

    $sf_cache = [];
    /*
     * Updated Loop
     */
    //Run this after rx_grouped query to ensure get_full_patient retrieves an accurate order profile
    $loop_timer = microtime(true);

    foreach ($changes['updated'] as $updated) {
        SirumLog::$subroutine_id = "rxs-single-updated2-".sha1(serialize($updated));

        $changed = changed_fields($updated);
        $patient = getPatientByRx($updated['rx_number']);
        AuditLog::log(
            sprintf(
                "Rx# %s for %s updated: %s",
                $updated['rx_number'],
                $updated['drug_name'],
                implode(
                    ', ',
                    array_map(
                        function ($v, $k) {
                            return sprintf("%s='%s'", $k, $v);
                        },
                        $changed,
                        array_keys($changed)
                    )
                )
            ),
            $patient
        );

        SirumLog::debug(
            "update_rxs_single2: rx updated $updated[drug_name] $updated[rx_number]",
            [
                'updated' => $updated,
                'changed' => $changed,
                'source'  => 'CarePoint',
                'type'    => 'rxs-single',
                'event'   => 'updated2'
            ]
        );

        if ($updated['rx_autofill'] != $updated['old_rx_autofill']) {
            $item = load_full_item($updated, $mysql, true);

            if (! $item['refills_used'] and $updated['rx_autofill']) {
                continue; //Don't log when a patient first registers
            }

            SirumLog::debug(
                "update_rxs_single2: about to call export_cp_rx_autofill()",
                [
                    'item'    => $item,
                    'updated' => $updated,
                    'source'  => 'CarePoint',
                    'type'    => 'rxs-single',
                    'event'   => 'updated'
                ]
            );

            //We need this because even if we change all rxs at the same time, pharmacists
            //Wmight just switch one Rx in CP so we need this to maintain consistency
            export_cp_rx_autofill($item, $mssql);

            $status  = $updated['rx_autofill'] ? 'ON' : 'OFF';
            $body    = "$item[drug_name] autofill turned $status for $updated[rx_number]"; //Used as cache key
            $created = "Created:".date('Y-m-d H:i:s');

            AuditLog::log(
                sprintf(
                    "Autofill for #%s for %s changed to %s.  Updating all Rx's with
                     same GSN to be on/off Autofill.",
                    $updated['rx_number'],
                    $updated['drug_name'],
                    $updated['rx_autofill']
                ),
                $patient
            );

            SirumLog::notice(
                "update_rxs_single2 rx_autofill changed.  Updating all Rx's with
                 same GSN to be on/off Autofill. Confirm correct updated rx_messages",
                [
                    'cache_key' => $body,
                    'sf_cache' => $sf_cache,
                    'updated' => $updated,
                    'changed' => $changed,
                    'item' => $item
                ]
            );

            if (! @$sf_cache[$body]) {
                $sf_cache[$body] = true; //This caches it by body and for this one run (currently 10mins)

                $salesforce = [
                  "subject"   => "Autofill turned $status for $updated[drug_name]",
                  "body"      => "$body $created",
                  "contact"   => "$item[first_name] $item[last_name] $item[birth_date]"
                ];

                $event_title = @$item['drug_name']." Autofill $status $salesforce[contact] $created";

                create_event($event_title, [$salesforce]);
            }
        }

        if ($updated['rx_gsn'] != $updated['old_rx_gsn']) {

          SirumLog::warning(
            "update_rxs_single2 rx_gsn updated (after rxs_grouped)",
            [
              'updated' => $updated,
              'changed' => $changed
            ]
          );

          //compliment method, update_rxs_single_drug, was already called before rx_grouped
          update_order_item_drug($mysql, $updated['rx_number']);
        }

        if ($updated['rx_transfer'] and ! $updated['old_rx_transfer']) {
            $item = load_full_item($updated, $mysql, true);
            $is_will_transfer = is_will_transfer($item);

            AuditLog::log(
                sprintf(
                    "Rx# %s for %s was marked to be transfered.  It %s be transfered because %s",
                    $updated['rx_number'],
                    $updated['drug_name'],
                    ($is_will_transfer) ? 'will' : 'will NOT',
                    $item['rx_message_key']
                ),
                $patient
            );

            SirumLog::warning(
                "update_rxs_single2 rx was transferred out.  Confirm correct is_will_transfer
                updated rxs_single.rx_message_key. rxs_grouped.rx_message_keys
                will be updated on next pass",
                [
                    'is_will_transfer' => $is_will_transfer,
                    'item'             => $item,
                    'updated'          => $updated,
                    'changed'          => $changed
                ]
            );
        }
    }
    log_timer('rx-singles-updated2', $loop_timer, $count_updated);

    /**
     * All RX should have a rx_message set.  We are going to query the database
     * and look for any with a NULL rx_message_key.  If we find one, load_full_item()
     * should retry updating the message?
     *
     *  NOTE Using an INNER JOIN to exclude Rxs associated with patients that are inactive or deceased
     */
    $loop_timer = microtime(true);
    $rx_singles = $mysql->run("
      SELECT *
      FROM
        gp_rxs_single
      JOIN gp_patients ON
        gp_patients.patient_id_cp = gp_rxs_single.patient_id_cp
      WHERE
        rx_message_key IS NULL"
    )[0];

    foreach ($rx_singles as $rx_single) {
        SirumLog::$subroutine_id = "rxs-single-null-message-".sha1(serialize($rx_single));

        //These should have been given an rx_message upon creation.  Why was it missing?
        SirumLog::error(
            "rx had an empty message, so just set it.  Why was it missing?",
            [
              "patient_id_cp" => $rx_single['patient_id_cp'],
              "patient_id_wc" => $rx_single['patient_id_wc'],
              "rx_single"     => $rx_single,
              "source"        => "CarePoint",
              "type"          => "rxs-single",
              "event"         => "null-message"
            ]
        );

        //This will retry setting the rx_messages
        $item = load_full_item($rx_single, $mysql);
    }
    log_timer('rx-singles-empty-messages', $loop_timer, count($rx_singles));


    SirumLog::resetSubroutineId();

  //TODO if new Rx arrives and there is an active order where that Rx is not included because of "ACTION NO REFILLS" or "ACTION RX EXPIRED" or the like, then we should rerun the helper_days_and_message on the order_item

  //TODO Implement rx_status logic that was in MSSQL Query and Save in Database

  //TODO Maybe? Update Salesforce Objects using REST API or a MYSQL Zapier Integration

  //TODO THIS NEED TO BE UPDATED TO MYSQL AND TO INCREMENTAL BASED ON CHANGES

  //TODO Add Group by "Qty per Day" so its GROUP BY Pat Id, Drug Name,

  //TODO GCN Updates Here Should Update Any Order Item That is has not GCN match
}
