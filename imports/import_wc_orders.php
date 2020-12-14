<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

use Sirum\Logging\SirumLog;


/**
 * QUESTION: Old code for finding duplicates.  Do we need this anymore?
 * DETECT DUPLICATES
 * SELECT invoice_number, COUNT(*) as counts FROM gp_orders_wc GROUP BY invoice_number HAVING counts > 1
 */


/**
 * Pull all the details from woocommerce into a table.  We will use this table
 * to compare to the last execution and find changes
 * @return void
 **/
function import_wc_orders() {

  $mysql = new Mysql_Wc();

  $duplicates = $mysql->run("
    SELECT
      meta_key,
      meta_value,
      GROUP_CONCAT(post_status) as post_status,
      COUNT(*) as number
    FROM `wp_postmeta`
    JOIN wp_posts ON post_id = wp_posts.ID
    WHERE meta_key = 'invoice_number'
    AND post_status != 'trash'
    GROUP BY meta_value
    HAVING number > 1
  ");

  /* Make sure that there aren't any duplicates.
   * If there are duplicate invoices, its a major error and
   * QUESTION: How do we actually get duplicates needs to stop execution
   */
  if (count($duplicates[0])) {
    $duplicate_invoices = 'Duplicate Invoice Numbers in WC. Stopping Cron Until Fixed';
    echo $duplicate_invoices;
    print_r($duplicates[0]);
    SirumLog::critical($duplicate_invoices, $duplicates[0]);
    // Push any lagging logs to google Cloud
    SirumLog::flush();
    exit;
  }


  /*
   * Grab all the orders out of woocommerce to create a snapshot
   * of the current invoices We will compare these with the last time the
   * script ran to find the differences
   */
  $orders = $mysql->run("

  SELECT

    MAX(CASE WHEN wp_postmeta.meta_key = '_customer_user' then wp_postmeta.meta_value ELSE NULL END) as patient_id_wc,
    MAX(CASE WHEN wp_postmeta.meta_key = 'invoice_number' then wp_postmeta.meta_value ELSE NULL END) as invoice_number,
    wp_posts.post_status as order_stage_wc,
    wp_posts.post_excerpt as order_note,

    MAX(
      CASE
        WHEN wp_postmeta.meta_key = 'rx_source' AND wp_postmeta.meta_value = 'refill' THEN 'Webform Refill'
        WHEN wp_postmeta.meta_key = 'rx_source' AND wp_postmeta.meta_value = 'pharmacy' THEN 'Webform Transfer'
        WHEN wp_postmeta.meta_key = 'rx_source' AND wp_postmeta.meta_value = 'erx' THEN 'Webform eRx'
        WHEN wp_postmeta.meta_key = 'order_source' THEN wp_postmeta.meta_value -- Unlike the others this is not the original, instead this is the CP source which was saved to the order
        ELSE NULL
      END
    ) as order_source,
    MAX(CASE WHEN wp_postmeta.meta_key = '_payment_method' then wp_postmeta.meta_value ELSE NULL END) as payment_method_actual,
    MAX(CASE WHEN wp_postmeta.meta_key = '_coupon_lines' then wp_postmeta.meta_value ELSE NULL END) as coupon_lines,

    -- MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_first_name' then wp_postmeta.meta_value ELSE NULL END) as first_name,
    -- MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_last_name' then wp_postmeta.meta_value ELSE NULL END) as last_name,
    -- MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_email' then wp_postmeta.meta_value ELSE NULL END) as email,
    -- MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_phone' then wp_postmeta.meta_value ELSE NULL END) as primary_phone,
    -- MAX(CASE WHEN wp_postmeta.meta_key = '_billing_phone' then wp_postmeta.meta_value ELSE NULL END) as secondary_phone,
    MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_address_1' then wp_postmeta.meta_value ELSE NULL END) as order_address1,
    MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_address_2' then wp_postmeta.meta_value ELSE NULL END) as order_address2,
    MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_city' then wp_postmeta.meta_value ELSE NULL END) as order_city,
    MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_state' then wp_postmeta.meta_value ELSE NULL END) as order_state,
    MAX(CASE WHEN wp_postmeta.meta_key = '_shipping_postcode' then LEFT(wp_postmeta.meta_value, 5) ELSE NULL END) as order_zip

  FROM
    wp_posts
  LEFT JOIN wp_postmeta ON wp_postmeta.post_id = wp_posts.ID
  WHERE
    post_type = 'shop_order'
  GROUP BY
     wp_posts.ID
  HAVING
    invoice_number > 0
  ");

  /*
   * No reason to continue if there aren't any orders.
   * QUESTION:  When would this ever return no values?
   */
  if ( ! count($orders[0])) return log_error('No Wc Orders to Import', get_defined_vars());

  /*
   * We are looping over everything to change the coupon code
   * to the correct value
   */
  $keys = result_map($orders[0],
    function($row) {

      /*
       * QUESTION: What is goodpill_wc_coupons_payment_gateway?  is this a valid "error"
       * or should it be a lower level like notice or debug.  Since we do so
       * much data formatting in the SQL query, why don't we handle this there?
       */
      if ($row['payment_method_actual'] == 'goodpill_wc_coupons_payment_gateway') {
        log_error('payment_method_actual goodpill_wc_coupons_payment_gateway >>> coupon', $row);
        $row['payment_method_actual'] = PAYMENT_METHOD['COUPON'];
      }

      return $row;
    }
  );

  /*
   * Replace the currently existing table
   * TODO: Need to look for a more efficient way to handle this
   */
  $mysql->replace_table("gp_orders_wc", $keys, $orders[0]);
}
