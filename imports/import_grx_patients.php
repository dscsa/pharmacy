<?php

require_once 'dbs/mssql_grx.php';
require_once 'dbs/mysql_webform.php';
require_once 'helpers/escape_vals.php';

function import_grx_patients() {

  $mssql = new Mssql_Grx();
  $mysql = new Mysql_Webform();

  $patients = $mssql->run("

    SELECT
      pat.pat_id as guardian_id,
      fname as first_name,
      lname as last_name,
      CAST(birth_date as date) as birth_date,

      CONCAT(ph1.area_code, ph1.phone_no) as phone1,
      CONCAT(ph2.area_code, ph2.phone_no) as phone2,
      pat.email as email,
      pat.auto_refill_cn as pat_autofill,

      user_def_1 as user_def1,
      user_def_2 as user_def2,
      user_def_3 as user_def3,
      user_def_4 as user_def4,

      addr1 as address1,
      addr2 as address2,
      a.city as city,
      a.state_cd as state,
      a.zip as zip,

      ISNULL(SELECT COUNT(*) FROM cprx WHERE cprx.pat_id = pat.pat_id AND orig_disp_date < GETDATE() - 4, 0) as total_fills, --potential to SUM(is_refill) but seems that GCNs churn enough that this is not accurate
      pat.pat_status_cn as pat_status,
      primary_lang_cd as lang,
      pat.add_date as pat_add_date
    FROM cppat pat (nolock)
    LEFT OUTER JOIN cppat_phone pp1 (nolock) ON pat.pat_id = pp1.pat_id AND (pp1.phone_type_cn = 6 OR pp1.phone_type_cn IS NULL)
    LEFT OUTER JOIN cppat_phone pp2 (nolock) ON pat.pat_id = pp2.pat_id AND (pp2.phone_type_cn = 9 OR pp2.phone_type_cn IS NULL)
    LEFT OUTER JOIN csphone ph1 (nolock) ON pp1.phone_id = ph1.phone_id
    LEFT OUTER JOIN csphone ph2 (nolock) ON pp2.phone_id = ph2.phone_id
    LEFT OUTER JOIN cppat_addr pa  (nolock) ON (pat.pat_id = pa.pat_id and pa.addr_type_cn=2)
    LEFT OUTER JOIN csaddr a (nolock) ON pa.addr_id=a.addr_id

  ");

  $keys = array_keys($patients[0]);
  $vals = escape_vals($patients);

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_patients_grx');

  $mysql->run("
    INSERT INTO gp_patients_grx (".implode(',', $keys).") VALUES ".implode(',', $vals)
  );
}
