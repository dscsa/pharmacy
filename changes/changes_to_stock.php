<?php

require_once 'dbs/mysql_webform.php';

function changes_to_rxs_single() {
  $mysql = new Mysql_Webform();

  $upserts = $mysql->run("
    SELECT staging.*
    FROM
      gp_rxs_cp as staging
    LEFT JOIN gp_rxs as rxs ON
      rxs.guardian_id <=> staging.guardian_id AND
      rxs.gcn_seqno <=> staging.gcn_seqno AND
      rxs.refills_total <=> staging.refills_total AND
      rxs.rx_autofill <=> staging.rx_autofill AND
      rxs.autofill_date <=> staging.autofill_date
    WHERE
      rxs.guardian_id IS NULL
  ");

  $removals = $mysql->run("
    SELECT rxs.*
    FROM
      gp_rxs_cp as staging
    RIGHT JOIN gp_rxs as rxs ON
      rxs.guardian_id <=> staging.guardian_id AND
      rxs.gcn_seqno <=> staging.gcn_seqno AND
      rxs.refills_total <=> staging.refills_total AND
      rxs.rx_autofill <=> staging.rx_autofill AND
      rxs.autofill_date <=> staging.autofill_date
    WHERE
      staging.guardian_id IS NULL
  ");

  $mysql->run("
    INSERT INTO gp_rxs (guardian_id, refills_total, gcn_seqno, drug_name, cprx_drug_name, rx_autofill, autofill_date, last_rxdisp_id, expire_date, oldest_script_high_refills, oldest_script_with_refills, oldest_active_script, newest_script)
    SELECT staging.guardian_id, staging.refills_total, staging.gcn_seqno, staging.drug_name, staging.cprx_drug_name, staging.rx_autofill, staging.autofill_date, staging.last_rxdisp_id, staging.expire_date, staging.oldest_script_high_refills, staging.oldest_script_with_refills, staging.oldest_active_script, staging.newest_script
    FROM gp_rxs_cp as staging
    LEFT JOIN gp_rxs as rxs ON
      rxs.guardian_id <=> staging.guardian_id AND
      rxs.gcn_seqno <=> staging.gcn_seqno AND
      rxs.refills_total <=> staging.refills_total AND
      rxs.rx_autofill <=> staging.rx_autofill AND
      rxs.autofill_date <=> staging.autofill_date
    WHERE
      rxs.guardian_id IS NULL
    ON DUPLICATE KEY UPDATE
      guardian_id = staging.guardian_id,
      refills_total = staging.refills_total,
      gcn_seqno = staging.gcn_seqno,
      drug_name = staging.drug_name,
      cprx_drug_name = staging.cprx_drug_name,
      rx_autofill = staging.rx_autofill,
      autofill_date = staging.autofill_date,
      last_rxdisp_id = staging.last_rxdisp_id,
      expire_date = staging.expire_date,
      oldest_script_high_refills = staging.oldest_script_high_refills,
      oldest_script_with_refills = staging.oldest_script_with_refills,
      oldest_active_script = staging.oldest_active_script,
      newest_script = staging.newest_script
  ");

  $mysql->run("
    DELETE rxs
    FROM gp_rxs_cp as staging
    RIGHT JOIN gp_rxs as rxs ON
      rxs.guardian_id <=> staging.guardian_id AND
      rxs.gcn_seqno <=> staging.gcn_seqno AND
      rxs.refills_total <=> staging.refills_total AND
      rxs.rx_autofill <=> staging.rx_autofill AND
      rxs.autofill_date <=> staging.autofill_date
    WHERE
      staging.guardian_id IS NULL
  ");

  return ['upserts' => $upserts[0], 'removals' => $removals[0]];
}
