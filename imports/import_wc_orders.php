<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

//DETECT DUPLICATES
//SELECT invoice_number, COUNT(*) as counts FROM gp_orders_wc GROUP BY invoice_number HAVING counts > 1

function import_wc_orders() {

  $mysql = new Mysql_Wc();

  $orders = $mysql->run("

  SELECT

    MAX(CASE WHEN wp_postmeta.meta_key = '_customer_user' then wp_postmeta.meta_value ELSE NULL END) as patient_id_wc,
    MAX(CASE WHEN wp_postmeta.meta_key = 'invoice_number' then wp_postmeta.meta_value ELSE NULL END) as invoice_number,
    wp_posts.post_status as order_stage_wc,
    wp_posts.post_excerpt as order_note,

    MAX(CASE WHEN wp_postmeta.meta_key = 'order_source' then wp_postmeta.meta_value ELSE NULL END) as order_source,
    MAX(CASE WHEN wp_postmeta.meta_key = '_payment_method' then wp_postmeta.meta_value ELSE NULL END) as payment_method,
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
    post_type    = 'shop_order'
  GROUP BY
     wp_posts.ID
  HAVING
    patient_id_wc > 0 AND
    invoice_number > 0
  ");

  if ( ! count($orders[0])) return log_error('No Wc Orders to Import', get_defined_vars());

  //log_info("
  //import_cp_orders: rows ".count($orders[0]));

  $keys = result_map($orders[0]);

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_orders_wc');

  $mysql->run("INSERT INTO gp_orders_wc $keys VALUES ".$orders[0]);
}
