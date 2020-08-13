<?php

include('mssql_new_drivers.php');
include('mssql_cp.php');

$mssql = new Mssql_Cp();

$results = $mssql->run("SELECT
      invoice_nbr as invoice_number,
      pat_id as patient_id_cp,
      ISNULL(liCount, 0) as count_items,
      ustate.name as order_source,
      CASE WHEN csom.ship_date IS NOT NULL AND ship.ship_date IS NULL THEN 'Dispensed' ELSE ostate.name END as order_stage_cp,
      CASE WHEN csom.ship_date IS NOT NULL AND ship.ship_date IS NULL THEN 'Dispensed' ELSE ostatus.descr END as order_status,
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
      --pat_id IS NOT NULL AND -- Some GRX orders link 1170 have no patient (now removed so now commented this out)!?
      ISNULL(status_cn, 0) <> 3 AND
      liCount > 0 --SureScript Authorization Denied, Webform eRx (before Rxs arrive), Webform Transfer (before transfer made)");

echo "<pre>";
print_r($results);