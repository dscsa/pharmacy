<?php

require_once 'dbs/mssql_cp.php';
require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

/**
 * Import all the orders for CarePoint (Guardian or cp)
 * @return void
 */
function import_cp_orders() {

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();

  $orders = $mssql->run("
    DECLARE @today as DATETIME
    SET @today = GETDATE()

    SELECT
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
      CASE
        WHEN
          csom_ship.tracking_code IS NULL
          AND order_state_cn <> 60
        THEN
          ship.ship_date
        ELSE
          CONVERT(varchar, COALESCE(ship.ship_date, csom.ship_date), 20)
      END as order_date_shipped,
      CASE WHEN order_state_cn = 60 THEN CONVERT(varchar, chg_date, 20) ELSE NULL END as order_date_returned,
      CONVERT(varchar, chg_date, 20) as order_date_changed
    FROM csom
      LEFT JOIN cp_acct ON cp_acct.id = csom.acct_id
      LEFT JOIN csct_code as ostate on (ostate.ct_id = 5000 and (isnull(csom.order_state_cn,0) = ostate.code_num))
      LEFT JOIN csct_code as ustate on (ustate.ct_id = 5007 and (isnull(csom.order_category_cn,0) = ustate.code_num))
      LEFT JOIN csomstatus as ostatus on (csom.order_state_cn = ostatus.state_cn and csom.order_status_cn = ostatus.omstatus)
      LEFT JOIN (SELECT order_id, MAX(ship_date) as ship_date FROM CsOmShipUpdate GROUP BY order_id) ship ON csom.order_id = ship.order_id -- CSOM_SHIP didn't always? update the tracking number within the day so use CsOmShipUpdate which is what endicia writes
      LEFT JOIN csom_ship ON csom.order_id = csom_ship.order_id -- CsOmShipUpdate won't have tracking numbers that Cindy inputted manually
    WHERE
      (ISNULL(status_cn, 0) <> 3 OR order_state_cn = 60) -- active or returned
      AND pat_id IS NOT NULL -- Some GRX orders link 1170, 32968 have no patient
      AND (
        order_state_cn < 50 OR -- active, not yet shipped, orders that are still in the queue
        csom.chg_date > @today - ".DAYS_OF_ORDERS_TO_IMPORT." -- Only recent orders to cut down on the import time (30-60s as of 2021-01-08).
      )
  ");

  if ( ! count($orders[0])) return log_error('No Cp Orders to Import', get_defined_vars());

  $keys = result_map($orders[0]);
  $mysql->replace_table("gp_orders_cp", $keys, $orders[0]);
}

/*
Guardian's Query to populate the built-in F9 QUEUE.  Use for reference on how to join supplemental tables

SELECT
--bt
--Top 10000
--et
 DisplayName =
 Case c.acct_type
  When 0 then pat.Lname+', '+pat.fname -- Individual
  When 1 Then pat.Lname+', '+pat.fname + ' (Family)'
  When 2 Then c.Company       -- Group
 end,
 c.company,
 c.acct_type,
 pat.lname,
 pat.fname,
 pat.mname,
 pat.title_lu,
 pat.suffix_lu,
 o.order_id,
 o.invoice_nbr,
 o.acct_id,
 o.order_state_cn,
 ostate.name as order_state_str,
 o.order_status_cn,
 ostatus.descr as order_status_str,
 o.hold_yn,
 o.priority_cn,
 priority.name as priority_str,
 shipping.name as shipping_str,
 o.rph_id,
 o.ship_cn,
[User]= u.initials,
 o.add_user_id,
 o.add_date,
 o.chg_user_id,
 o.chg_date,
 o.ship_date, // = case when order_state_cn = 50 then o.chg_date else NULL end,
 order_line_count = o.liCount,
 class2_count = o.c2Count,
 o.store_id,
 o.Expected_by,
 owner_store_id
 ,o.status_cn
 ,Category=ustate.name
 ,DestStateCD
 ,LockedBy = CASE
   when locked_yn > 0 then u2.lname +IsNull(', '+u2.Fname,'')
   else Convert(varchar(60),'') end

FROM CsOm o  (NOLOCK)
  JOIN CUST c (NOLOCK) on o.acct_id = cast(c.id as integer)
  LEFT outer JOIN cppat as pat (NOLOCK) on c.pat_id = pat.pat_id
  LEFT outer JOIN csct_code as priority (NOLOCK) on (priority.ct_id = 5001 and (isnull(o.priority_cn,0) = priority.code_num))
  LEFT outer JOIN csct_code as ostate (NOLOCK) on (ostate.ct_id = 5000 and (isnull(o.order_state_cn,0) = ostate.code_num))
  LEFT outer JOIN csct_code as ustate (NOLOCK) on (ustate.ct_id = 5007 and (isnull(o.order_category_cn,0) = ustate.code_num))
  LEFT outer JOIN CsOmStatus as ostatus (NOLOCK) on (o.order_state_cn = ostatus.state_cn and o.order_status_cn = ostatus.omstatus)
  LEFT outer JOIN cp_shipping as shipping (NOLOCK) on (shipping.shipping_id = o.ship_cn)
  LEFT outer JOIN csuser u2 (NOLOCK) on lock_user_id = u2.user_id
--%BEGIN_JOIN%
  LEFT outer JOIN csuser u (NOLOCK) on o.rph_id = u.user_id
--%END_JOIN%

WHERE
//BS
order_status_cn in (9,10,50,100,105,108,109,110,111,113,130,135,137,138,139,150,152,198,200,201,202,203,204,205,206,207,208,209,210,220,221,300,1001,1002,1003,1004,1005,1006,1007,1008,1009,1010,1011,1012,1013,1014,1100,1101,1102,1103,1104,1105,1200,3001,4000,4001,4002,4100,4101)
//ES
--%BEGIN_WHERE%
 and (o.store_id = 1) and (order_state_cn <> 50)
--%END_WHERE%

--%BEGIN_ORDERBY%
order by DisplayName DESC
--%END_ORDERBY%
*/
