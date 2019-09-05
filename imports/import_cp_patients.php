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

      MAX(CONCAT(ph1.area_code, ph1.phone_no)) as phone1,
      MAX(CONCAT(ph2.area_code, ph2.phone_no)) as phone2,
      MAX(pat.email) as email,
      MAX(pat.auto_refill_cn) as patient_autofill,

      MAX(user_def_1) as pharmacy_name,
      MAX(user_def_2) as pharmacy_info,
      MAX(user_def_3) as payment_method,
      MAX(user_def_4) as billing_info,

      MAX(addr1) as patient_address1,
      MAX(addr2) as patient_address2,
      MAX(a.city) as patient_city,
      MAX(a.state_cd) as patient_state,
      MAX(a.zip) as patient_zip,

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
    GROUP BY pat.pat_id -- because cppat_phone had a duplicate entry for pat_id 5130 we got two rows so need a groupby.  This also removes refills_used from needing to be a subquery

  ");

  $keys = result_map($patients[0],
    function($row) {

      //This is hard todo in MSSQL so doing it in PHP instead
      //These were single quoted by clean_val() already so need to have quotes striped
      $val1 = $row['pharmacy_info'] == 'NULL' ? 'NULL' : substr($row['pharmacy_info'], 1, -1);
      $val2 = $row['billing_info']  == 'NULL' ? 'NULL' : substr($row['billing_info'], 1, -1);

      $val1 = explode(',', $val1) + ['', '', '', ''];
      $val2 = explode(',', $val2) + ['', '', '', ''];

      //echo 'result_map: '.print_r($val1, true).' '.print_r($val2, true);

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
            echo "Error with card expiration date $date_expired: ".$val2[1]." ".print_r($row, true);
            $row['payment_card_date_expired'] = 'NULL';
          }

        }
      }

      if ($val2[3] && substr($val2[3], 0, 6) != "track_") {
        $row['tracking_coupon'] = 'NULL';
        $row['payment_coupon']  = clean_val($val2[3]);
        assert_length($row, 'payment_coupon', 5, 40); //with single quotes
      } else {
        $row['payment_coupon']  = 'NULL';
        $row['tracking_coupon'] = clean_val($val2[3]);
        assert_length($row, 'tracking_coupon', 5, 40); //with single quotes
      }


      //Some validations
      assert_length($row, 'pharmacy_npi', 12);
      assert_length($row, 'pharmacy_fax', 12);
      assert_length($row, 'pharmacy_phone', 12);

      assert_length($row, 'payment_card_last4', 6);
      assert_length($row, 'payment_card_type', 4, 20);

      $next_month = date('Y-m-d', strtotime('+1 month'));

      if ($row['payment_coupon'] != 'NULL') {
        $row['payment_method'] = "'".PAYMENT_METHOD['COUPON']."'";
      }
      else if ($row['payment_card_date_expired'] == 'NULL' ) {
        $row['payment_method'] = "'".PAYMENT_METHOD['MANUAL']."'";
      }
      else if ($row['payment_card_date_expired'] > "'$next_month'") {
        $row['payment_method'] = "'".PAYMENT_METHOD['AUTOPAY']."'";
      }
      else {
        $row['payment_method'] = "'".PAYMENT_METHOD['CARD EXPIRED']."'";
      }

      unset($row['billing_info']);
      unset($row['pharmacy_info']);

      return $row;
    }
  );

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_patients_cp');

  $mysql->run("INSERT INTO gp_patients_cp $keys VALUES ".$patients[0]);
}
