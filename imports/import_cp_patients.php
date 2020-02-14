<?php

require_once 'dbs/mssql_cp.php';
require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_cp_patients() {

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();




  $patients = $mssql->run("

    SELECT
      pat.pat_id as patient_id_cp,
      MAX(fname) as first_name,
      MAX(lname) as last_name,
      CONVERT(varchar, MAX(birth_date), 20) as birth_date,

      CONVERT(text, MAX(NULLIF(SUBSTRING(pat.cmt, 0, ISNULL(NULLIF(CHARINDEX(CHAR(10)+'___', pat.cmt), 0), 9999)), ''))) as patient_note,
      CONVERT(text, MAX(NULLIF(SUBSTRING(pat.cmt, ISNULL(NULLIF(NULLIF(CHARINDEX('___'+CHAR(13), pat.cmt)+3, 3), DATALENGTH(pat.cmt)), 9999), 9999), ''))) as medications_other,

      NULLIF(MAX(CONCAT(ph1.area_code, ph1.phone_no)), '') as phone1,
      NULLIF(MAX(CONCAT(ph2.area_code, ph2.phone_no)), '') as phone2,
      NULLIF(MAX(pat.email), '') as email,
      MAX(pat.auto_refill_cn) as patient_autofill,

      MAX(user_def_1) as pharmacy_name,
      MAX(user_def_2) as pharmacy_info,
      MAX(user_def_3) as payment_method_default,
      MAX(user_def_4) as billing_info,

      NULLIF(MAX(addr1), '') as patient_address1,
      NULLIF(MAX(addr2), '') as patient_address2,
      NULLIF(MAX(a.city), '') as patient_city,
      NULLIF(MAX(a.state_cd), '') as patient_state,
      NULLIF(MAX(a.zip), '') as patient_zip,

      MAX(CASE WHEN Dam_agcsp = 900388 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'No Known Allergies' ELSE NULL END) as allergies_none,
      MAX(CASE WHEN Dam_agcsp = 478 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Tetracyclines' ELSE NULL END) as allergies_tetracycline,
      MAX(CASE WHEN Dam_agcsp = 477 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Cephalosporins' ELSE NULL END) as allergies_cephalosporins,
      MAX(CASE WHEN Dam_agcsp = 491 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Sulfa' ELSE NULL END) as allergies_sulfa,
      MAX(CASE WHEN hic ='H3DB' AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Aspirin' ELSE NULL END) as allergies_aspirin,
      MAX(CASE WHEN Dam_agcsp = 476 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Penicillin' ELSE NULL END) as allergies_penicillin,
      MAX(CASE WHEN hic ='W1DA' AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Erythromycin' ELSE NULL END) as allergies_erythromycin,
      MAX(CASE WHEN hic ='H3AH' AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Codeine' ELSE NULL END) as allergies_codeine,
      MAX(CASE WHEN Dam_agcsp = 439 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'NSAIDS' ELSE NULL END) as allergies_nsaids,
      MAX(CASE WHEN Dam_agcsp = 270 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Salicylates' ELSE NULL END) as allergies_salicylates,
      MAX(CASE WHEN hic ='W1DH' AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Azithromycin' ELSE NULL END) as allergies_azithromycin,
      MAX(CASE WHEN hic ='W1AU' AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN 'Amoxicillin' ELSE NULL END) as allergies_amoxicillin,
      MAX(CASE WHEN hic ='' AND Dam_agcsp = 0 AND ISNULL(cppat_alr.status_cn, 0) <> 3 THEN name ELSE NULL END) as allergies_other,

      SUM(refills_orig + 1 - refills_left) as refills_used, --potential to SUM(is_refill) but seems that GCNs churn enough that this is not accurate
      MAX(pat.pat_status_cn) as patient_status,
      MAX(ISNULL(primary_lang_cd, 'EN')) as language,
      CONVERT(varchar, MAX(pat.add_date), 20) as patient_date_added,
      CONVERT(varchar, MAX(pat.chg_date), 20) as patient_date_changed
    FROM cppat pat (nolock)
    LEFT JOIN cppat_phone pp1 (nolock) ON pat.pat_id = pp1.pat_id AND pp1.phone_type_cn = 6
    LEFT JOIN cppat_phone pp2 (nolock) ON pat.pat_id = pp2.pat_id AND pp2.phone_type_cn = 9
    LEFT JOIN csphone ph1 (nolock) ON pp1.phone_id = ph1.phone_id
    LEFT JOIN csphone ph2 (nolock) ON pp2.phone_id = ph2.phone_id
    LEFT JOIN cppat_addr pa  (nolock) ON (pat.pat_id = pa.pat_id and pa.addr_type_cn=2)
    LEFT JOIN csaddr a (nolock) ON pa.addr_id=a.addr_id
    LEFT JOIN cprx ON cprx.pat_id = pat.pat_id AND orig_disp_date < GETDATE() - 4
    LEFT JOIN cppat_alr ON cppat_alr.pat_id = pat.pat_id
    WHERE birth_date IS NOT NULL -- pat.pat_id = 5869
    GROUP BY pat.pat_id -- because cppat_phone had a duplicate entry for pat_id 5130 we got two rows so need a groupby.  This also removes refills_used from needing to be a subquery

  ");

  if ( ! count($patients[0])) return log_error('No Cp Patients to Import', get_defined_vars());


  //log_info("
  //import_cp_patients: rows ".count($patients[0]));

  $keys = result_map($patients[0],
    function($row) {

      //This is hard todo in MSSQL so doing it in PHP instead
      //These were single quoted by clean_val() already so need to have quotes striped
      $val1 = $row['pharmacy_info'] == 'NULL' ? 'NULL' : substr($row['pharmacy_info'], 1, -1);
      $val2 = $row['billing_info']  == 'NULL' ? 'NULL' : substr($row['billing_info'], 1, -1);

      $val1 = explode(',', $val1) + ['', '', '', ''];
      $val2 = explode(',', $val2) + ['', '', '', ''];

      //log('result_map: '.print_r($val1, true).' '.print_r($val2, true));

      $row['pharmacy_name']    = $row['pharmacy_name'] ?: 'NULL';
      $row['pharmacy_npi']     = clean_val($val1[0]);
      $row['pharmacy_fax']     = clean_phone($val1[1]);
      $row['pharmacy_phone']   = clean_phone($val1[2]);
      $row['pharmacy_address'] = clean_val($val1[3]);

      $row['payment_card_last4']        = clean_val($val2[0]);
      $row['payment_card_date_expired'] = clean_val($val2[1]);
      $row['payment_card_type']         = clean_val($val2[2]);

      if ($val2[1]) {
        if ($val2[1] == "/") {
          $row['payment_card_date_expired'] = 'NULL';
        } else {
          $date_expired = date_create_from_format("m/y", $val2[1]);

          if ($date_expired) {
            $row['payment_card_date_expired'] = date_format($date_expired, "'Y-m-t'"); //t give last day of month.  d was givign current day
          }
          else {
            log_error("import_cp_patients: error with card expiration date $date_expired", get_defined_vars());
            $row['payment_card_date_expired'] = 'NULL';
          }

        }
      }

      if ( ! $val2[3]) {
        $row['tracking_coupon'] = 'NULL';
        $row['payment_coupon']  = 'NULL';
      }
      else if (substr($val2[3], 0, 6) == "track_") {
        log_info("Really Tracking Coupon???", get_defined_vars());
        $row['payment_coupon']  = 'NULL';
        $row['tracking_coupon'] = clean_val($val2[3]);
        assert_length($row, 'tracking_coupon', 5, 40); //with single quotes
      }
      else {
        $row['tracking_coupon'] = 'NULL';
        $row['payment_coupon']  = clean_val($val2[3]);
        assert_length($row, 'payment_coupon', 5, 40); //with single quotes
      }


      //Some validations
      assert_length($row, 'pharmacy_npi', 12);
      assert_length($row, 'pharmacy_fax', 12);
      assert_length($row, 'pharmacy_phone', 12);

      assert_length($row, 'payment_card_last4', 6);
      assert_length($row, 'payment_card_type', 4, 20);

      $next_month = date('Y-m-d', strtotime('+1 month'));

      if ($row['payment_coupon'] != 'NULL') {
        $row['payment_method_default'] = "'".PAYMENT_METHOD['COUPON']."'";
      }
      else if ($row['payment_card_date_expired'] == 'NULL' ) {
        $row['payment_method_default'] = "'".PAYMENT_METHOD['MAIL']."'";
      }
      else if ($row['payment_card_date_expired'] > "'$next_month'") {
        $row['payment_method_default'] = "'".PAYMENT_METHOD['AUTOPAY']."'";
      }
      else {
        $row['payment_method_default'] = "'".PAYMENT_METHOD['CARD EXPIRED']."'";
      }

      unset($row['billing_info']);
      unset($row['pharmacy_info']);

      return $row;
    }
  );

  //Replace Staging Table with New Data
  $sql = "INSERT INTO gp_patients_cp $keys VALUES ".$patients[0];

  $mysql->run("START TRANSACTION");
  $mysql->run("DELETE FROM gp_patients_cp");
  $mysql->run($sql);
  $mysql->run("COMMIT");
}
