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
      fname as first_name,
      lname as last_name,
      CONVERT(varchar, birth_date, 20) as birth_date,

      CONCAT(ph1.area_code, ph1.phone_no) as phone1,
      CONCAT(ph2.area_code, ph2.phone_no) as phone2,
      pat.email as email,
      pat.auto_refill_cn as patient_autofill,

      user_def_1 as pharmacy_name,
      user_def_2 as pharmacy_info,
      user_def_3 as billing_method,
      user_def_4 as billing_info,

      addr1 as patient_address1,
      addr2 as patient_address2,
      a.city as patient_city,
      a.state_cd as patient_state,
      a.zip as patient_zip,

      (SELECT COUNT(*) FROM cprx WHERE cprx.pat_id = pat.pat_id AND orig_disp_date < GETDATE() - 4) as total_fills, --potential to SUM(is_refill) but seems that GCNs churn enough that this is not accurate
      pat.pat_status_cn as patient_status,
      ISNULL(primary_lang_cd, 'EN') as lang,
      CONVERT(varchar, pat.add_date, 20) as patient_date_added,
      CONVERT(varchar, pat.chg_date, 20) as patient_date_changed
    FROM cppat pat (nolock)
    LEFT OUTER JOIN cppat_phone pp1 (nolock) ON pat.pat_id = pp1.pat_id AND (pp1.phone_type_cn = 6 OR pp1.phone_type_cn IS NULL)
    LEFT OUTER JOIN cppat_phone pp2 (nolock) ON pat.pat_id = pp2.pat_id AND (pp2.phone_type_cn = 9 OR pp2.phone_type_cn IS NULL)
    LEFT OUTER JOIN csphone ph1 (nolock) ON pp1.phone_id = ph1.phone_id
    LEFT OUTER JOIN csphone ph2 (nolock) ON pp2.phone_id = ph2.phone_id
    LEFT OUTER JOIN cppat_addr pa  (nolock) ON (pat.pat_id = pa.pat_id and pa.addr_type_cn=2)
    LEFT OUTER JOIN csaddr a (nolock) ON pa.addr_id=a.addr_id

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
      $row['pharmacy_fax']     = clean_phone($val1[1]));
      $row['pharmacy_phone']   = clean_phone($val1[2]));
      $row['pharmacy_address'] = clean_val($val1[3]);

      $row['card_last4']        = clean_val($val2[0]);
      $row['card_date_expired'] = clean_val($val2[1]);
      $row['card_type']         = clean_val($val2[2]);
      $row['billing_coupon']    = clean_val($val2[3]);


      //echo 'result_map: '.print_r($row, true);
      if ($row['card_date_expired'] == "'/'") $row['card_date_expired'] = 'NULL'; //Assert would catch this but avoid noisy logging

      //Some validations
      assert_length($row['pharmacy_npi'], 12);      //no delimiters with single quotes
      assert_length($row['pharmacy_fax'], 12);      //no delimiters with single quotes
      assert_length($row['pharmacy_phone'], 12);    //no delimiters with single quotes

      assert_length($row['card_last4'], 6);         //with single quotes
      assert_length($row['card_date_expired'], 6, 7);  //with single quotes
      assert_length($row['card_type'], 4, 20);      //with single quotes
      assert_length($row['billing_coupon'], 5, 40); //with single quotes

      if ($row['card_date_expired'] != 'NULL') {
        //echo 'result_map: '.print_r($row, true);
        $row['card_date_expired'] = date_format(date_create_from_format("'m/y'", $row['card_date_expired']), "'Y-m-d'");
        //echo 'result_map: '.print_r($row, true);
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
