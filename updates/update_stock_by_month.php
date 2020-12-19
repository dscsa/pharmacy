<?php
require_once 'dbs/mysql_wc.php';

use Sirum\Logging\SirumLog;

/**
 * Use a transaction to update the the current stock levels.  This DELETES all
 * records from the table then repopulates everything with a query.  Finally
 * logs an error if duplicate GSNs are found.
 *
 * @return void
 */

function update_stock_by_month($changes) {

  $month_interval  = 4; //This is full months, excluding the current partial month that is included, so on average it will be 0.5 months more than this number
  $default_rxs_min = 3;

  $count_deleted  = count($changes['deleted']);
  $count_created  = count($changes['created']);
  $count_updated  = count($changes['updated']);

  if ( ! $count_deleted AND ! $count_created AND ! $count_updated) return;

  SirumLog::$subroutine_id = "stock-v2-".sha1(serialize($changes));

  //Overrite Rx Messages everytime a new order created otherwis same message would stay for the life of the Rx

  SirumLog::debug(
    "update_stock_by_month: updating gp_stock_live",
    [
        'changes' => $changes,
        'source'  => 'v2',
        'type'    => 'stock',
        'event'   => 'created, updated, or deleted'
    ]
  );

  //log_info("update_stock_by_month: $count_deleted deleted, $count_created created, $count_updated updated.", get_defined_vars());

  $mysql = new Mysql_Wc();

  $sql1 = "
    INSERT INTO gp_stock_live
    SELECT

      * ,

      IF(
        zlow_threshold IS NULL OR zhigh_threshold IS NULL,
        'PRICE ERROR',
        IF(
          drug_ordered IS NULL,
          IF(zscore > zhigh_threshold, 'ORDER DRUG', 'NOT OFFERED'),

          IF(
            last_inventory < last_inv_low_threshold,
            'OUT OF STOCK',

            IF(
              zscore < zlow_threshold,
              IF(total_dispensed_actual > last_inventory/10 OR last_inventory < 1000, 'REFILL ONLY', 'ONE TIME'),

              IF(
               last_inventory < last_inv_high_threshold OR zscore < zhigh_threshold,
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

        -- YIKES!!! ORDER MATTERS HERE BECAUSE OF THE INSERT

        -- Drugs that are recently ordered and never dispensed should not be labeled out of stock
        -- Drugs that are being dispensed but have less than 2 week of inventory on hand should be out of stock AND at least default_rxs_min/2*repack_qty
        IF(total_dispensed_actual > 0, GREATEST(total_dispensed_actual/$month_interval/2, COALESCE(total_dispensed_default/2, 0)), 0) as last_inv_low_threshold,

        -- if we are already dispensing, we want 4 weeks (1 month) of inventory on hand before listing high supply
        -- if we are not already dispensing, we want 2 prescriptions of inventory on hand before listing high supply
        GREATEST(COALESCE(total_dispensed_actual/$month_interval, 0), COALESCE(total_dispensed_default, 0)) as last_inv_high_threshold,

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
           STDDEV_SAMP(IF(month <= CURDATE(), entered_sum, NULL)) as stddev_entered,
           SUM(entered_sum) as total_entered,

           GROUP_CONCAT(CONCAT(month, ' ', dispensed_sum) ORDER BY month ASC) as months_dispensed,
           -- Exclude current (partial) month from STD_DEV as it will look very different from full months
           IF(STDDEV_SAMP(IF(month <= CURDATE(), dispensed_sum, NULL)) > 0, STDDEV_SAMP(IF(month <= CURDATE(), dispensed_sum, NULL)), NULL) as stddev_dispensed_actual,
           IF(SUM(dispensed_sum) > 0, SUM(dispensed_sum), NULL) as total_dispensed_actual,

           $default_rxs_min*COALESCE(MAX(gp_drugs.qty_repack), 135) as total_dispensed_default,
           $default_rxs_min*COALESCE(MAX(gp_drugs.qty_repack), 135)/POWER($month_interval, .5) as stddev_dispensed_default

           FROM
            gp_stock_by_month
           JOIN gp_drugs ON
            gp_drugs.drug_generic = gp_stock_by_month.drug_generic

           WHERE
            month > (CURDATE() - INTERVAL $month_interval MONTH) AND
            month <= (CURDATE() + INTERVAL 1 MONTH)
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
    SELECT
      stock1.drug_generic as drug_generic1,
      stock2.drug_generic as drug_generic2,
      stock1.drug_gsns as drug_gsns1,
      stock2.drug_gsns as drug_gsns2
    FROM
      gp_stock_live stock1
    JOIN
      gp_stock_live stock2 ON stock1.drug_gsns LIKE CONCAT('%', stock2.drug_gsns, '%')
    WHERE
      stock1.drug_generic != stock2.drug_generic
  ");

  if (isset($duplicate_gsns[0][0])) {

    $salesforce   = [
      "subject"   => 'Duplicate GSNs in V2',
      "body"      => print_r($duplicate_gsns[0][0], true),
      "assign"    => "Joseph",
      "due_date"  => date('Y-m-d')
    ];

    $event_title = "$log $salesforce[due_date]";

    create_event($event_title, [$salesforce]);

    SirumLog::alert("update_stock_by_month: $salesforce", $duplicate_gsns[0]);
  }

  SirumLog::resetSubroutineId();


  //TODO Calculate Qty Per Day from Sig and save in database

  //TODO Clean Drug Name and save in database RTRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ISNULL(generic_name, cprx.drug_name), ' CAPSULE', ' CAP'),' CAPS',' CAP'),' TABLET',' TAB'),' TABS',' TAB'),' TB', ' TAB'),' HCL',''),' MG','MG'), '\"', ''))

  //TODO Implement rx_status logic that was in MSSQL Query and Save in Database

  //TODO Maybe? Update Salesforce Objects using REST API or a MYSQL Zapier Integration

  //TODO THIS NEED TO BE UPDATED TO MYSQL AND TO INCREMENTAL BASED ON CHANGES

  //TODO Add Group by "Qty per Day" so its GROUP BY Pat Id, Drug Name,
  //WE WANT TO INCREMENTALLY UPDATE THIS TABLE RATHER THAN DOING EXPENSIVE GROUPING QUERY ON EVERY READ
}
