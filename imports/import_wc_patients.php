<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

//DETECT DUPLICATES
//SELECT invoice_number, COUNT(*) as counts FROM gp_orders_wc GROUP BY invoice_number HAVING counts > 1

function import_wc_patients() {

  $mysql = new Mysql_Wc();

  $orders = $mysql->run("

  SELECT

    MAX(CASE WHEN wp_usermeta.meta_key = 'patient_id_cp' then wp_usermeta.meta_value ELSE NULL END) as patient_id_cp,
    wp_users.ID as patient_id_wc,
    MAX(CASE WHEN wp_usermeta.meta_key = 'first_name' then wp_usermeta.meta_value ELSE NULL END) as first_name,
    MAX(CASE WHEN wp_usermeta.meta_key = 'last_name' then wp_usermeta.meta_value ELSE NULL END) as last_name,
    RIGHT(user_login, 10) as birth_date,
    MAX(CASE WHEN wp_usermeta.meta_key = 'medications_other' then wp_usermeta.meta_value ELSE NULL END) as patient_note,

    -- https://stackoverflow.com/questions/37268248/how-to-get-only-digits-from-string-in-mysql
    RIGHT(0+MAX(CASE WHEN wp_usermeta.meta_key = 'phone' then wp_usermeta.meta_value ELSE NULL END), 10) as phone1,
    RIGHT(0+MAX(CASE WHEN wp_usermeta.meta_key = 'billing_phone' then wp_usermeta.meta_value ELSE NULL END), 10) as phone2,
    user_email as email,

    MAX(CASE WHEN wp_usermeta.meta_key = 'patient_autofill' then wp_usermeta.meta_value ELSE NULL END) as patient_autofill,
    MAX(CASE WHEN wp_usermeta.meta_key = 'backup_pharmacy' then wp_usermeta.meta_value ELSE NULL END) as backup_pharmacy,

    MAX(CASE WHEN wp_usermeta.meta_key = 'payment_card_type' then wp_usermeta.meta_value ELSE NULL END) as payment_card_type,
    MAX(CASE WHEN wp_usermeta.meta_key = 'payment_card_last4' then wp_usermeta.meta_value ELSE NULL END) as payment_card_last4,
    MAX(CASE WHEN wp_usermeta.meta_key = 'payment_card_date_expired' then wp_usermeta.meta_value ELSE NULL END) as payment_card_date_expired,
    MAX(CASE WHEN wp_usermeta.meta_key = 'payment_method_default' then wp_usermeta.meta_value ELSE NULL END) as payment_method_default,
    MAX(CASE WHEN wp_usermeta.meta_key = 'coupon' AND LEFT(wp_usermeta.meta_value, 6) = 'track' then wp_usermeta.meta_value ELSE NULL END) as tracking_coupon,
    MAX(CASE WHEN wp_usermeta.meta_key = 'coupon' AND LEFT(wp_usermeta.meta_value, 6) != 'track' then wp_usermeta.meta_value ELSE NULL END) as payment_coupon,

    MAX(CASE WHEN wp_usermeta.meta_key = 'billing_address_1' then wp_usermeta.meta_value ELSE NULL END) as patient_address1,
    MAX(CASE WHEN wp_usermeta.meta_key = 'billing_address_2' then wp_usermeta.meta_value ELSE NULL END) as patient_address2,
    MAX(CASE WHEN wp_usermeta.meta_key = 'billing_city' then wp_usermeta.meta_value ELSE NULL END) as patient_city,
    MAX(CASE WHEN wp_usermeta.meta_key = 'billing_state' then wp_usermeta.meta_value ELSE NULL END) as patient_state,
    LEFT(0+MAX(CASE WHEN wp_usermeta.meta_key = 'billing_postcode' then wp_usermeta.meta_value ELSE NULL END), 5) as patient_zip,
    MAX(CASE WHEN wp_usermeta.meta_key = 'language' then wp_usermeta.meta_value ELSE NULL END) as language,

    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_none' AND wp_usermeta.meta_value then 'No Known Allergies' ELSE NULL END) as allergies_none,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_tetracycline' AND wp_usermeta.meta_value then 'Tetracyclines' ELSE NULL END) as allergies_tetracycline,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_cephalosporins' AND wp_usermeta.meta_value then 'Cephalosporins' ELSE NULL END) as allergies_cephalosporins,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_sulfa' AND wp_usermeta.meta_value then 'Sulfa' ELSE NULL END) as allergies_sulfa,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_aspirin' AND wp_usermeta.meta_value then 'Aspirin' ELSE NULL END) as allergies_aspirin,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_penicillin' AND wp_usermeta.meta_value then 'Penicillin' ELSE NULL END) as allergies_penicillin,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_erythromycin' AND wp_usermeta.meta_value then 'Erythromycin' ELSE NULL END) as allergies_erythromycin,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_codeine' AND wp_usermeta.meta_value then 'Codeine' ELSE NULL END) as allergies_codeine,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_nsaids' AND wp_usermeta.meta_value then 'NSAIDS' ELSE NULL END) as allergies_nsaids,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_salicylates' AND wp_usermeta.meta_value then 'Salicylates' ELSE NULL END) as allergies_salicylates,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_azithromycin' AND wp_usermeta.meta_value then 'Azithromycin' ELSE NULL END) as allergies_azithromycin,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_amoxicillin' AND wp_usermeta.meta_value then 'Amoxicillin' ELSE NULL END) as allergies_amoxicillin,
    MAX(CASE WHEN wp_usermeta.meta_key = 'allergies_other' AND wp_usermeta.meta_value then wp_usermeta.meta_value ELSE NULL END) as allergies_other

  FROM
    wp_users
  LEFT JOIN
    wp_usermeta ON wp_usermeta.user_id = wp_users.ID
  WHERE
    MID(user_login, -10, 4) > 1900 AND -- validate birth_date in username so users with malformed birthdates or users like 'root'
    MID(user_login, -10, 4) < 2100
  GROUP BY
     wp_users.ID
  ");

  if ( ! count($orders[0])) return log_error('No Wc Orders to Import', get_defined_vars());

  $keys = result_map($orders[0],
    function($row) {

      $pharmacy = json_decode($row['backup_pharmacy'], true);

      $row['pharmacy_name'] = $pharmacy['name'] ?: 'NULL';
      $row['pharmacy_npi'] = $pharmacy['npi'] ?: 'NULL';
      $row['pharmacy_fax'] = $pharmacy['fax'] ?: 'NULL';
      $row['pharmacy_phone'] = $pharmacy['phone'] ?: 'NULL';
      $row['pharmacy_address'] = $pharmacy['address'] ?: 'NULL';

      unset($row['backup_pharmacy']);

      return $row;
    }
  );

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_patients_wc');

  $sql = "INSERT INTO gp_patients_wc $keys VALUES ".$orders[0];

  log_error("import_wc_patients: ".$sql);

  $mysql->run($sql);
}
