<?php
use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

function export_cp_patient_save_medications_other($mssql, $patient, $live = false)
{
    $medications_other = escape_db_values(utf8ize($patient['medications_other']));

    /*
    $select = "
      SELECT
        DATALENGTH(cmt) as cmt_length,
        CHARINDEX(CHAR(10), cmt) as char10_index,
        CHARINDEX(CHAR(13), cmt) as char13_index,
        CHARINDEX(CHAR(10)+'___', cmt) as divider_index,
        LEN(SUBSTRING(cmt, 0, ISNULL(NULLIF(CHARINDEX(CHAR(10)+'___', cmt), 0), 9999))) as first_length,
        SUBSTRING(cmt, 0, ISNULL(NULLIF(CHARINDEX(CHAR(10)+'___', cmt), 0), 9999)) as first,
        cmt
      FROM cppat
      WHERE pat_id = $patient[patient_id_cp]
    ";
    */
    $cp_automation_user = CAREPOINT_AUTOMATION_USER;

    $sql = "
    UPDATE cppat
    SET cmt =
      SUBSTRING(cmt, 0, ISNULL(NULLIF(CHARINDEX(CHAR(10)+'___', cmt), 0), 9999))+
      CHAR(10)+'______________________________________________'+CHAR(13)+
      '$medications_other',
      chg_user_id = {$cp_automation_user}
    WHERE pat_id = {$patient['patient_id_cp']}
  ";

    // echo "\n$sql";
    // echo "\nOld:{$patient['old_medications_other']}";
    // echo "\nNew:{$patient['medications_other']}";

    //$res1 = $mssql->run("$select");
    $mssql->run($sql);
    //$res2 = $mssql->run("$select");

  //echo "
  //live:$live $patient[first_name] $patient[last_name] $sql ".json_encode($res1, JSON_PRETTY_PRINT)." ".json_encode($res2, JSON_PRETTY_PRINT);
}

function update_cp_patient_active_status($mssql, $patient_id_cp, $inactive)
{
    $cp_automation_user = CAREPOINT_AUTOMATION_USER;
    if (! $patient_id_cp) {
        return;
    }

    if ($inactive == 'Inactive') {
        $cp_key = 'Inactivated';
        $cp_val = 2;
    } elseif ($inactive == 'Deceased') {
        $cp_key = 'Marked deceased';
        $cp_val = 3;
    } else {
        $cp_key = 'Reactivated';
        $cp_val = 1;
    }

    $date = date('Y-m-d H:i:s');

    $sql = "UPDATE cppat
            SET
                pat_status_cn = $cp_val,
                cmt = CONCAT(cmt, ' $cp_key by Pharmacy App on $date'),
                chg_user_id = {$cp_automation_user}
            WHERE
              pat_id = $patient_id_cp";

    $mssql->run($sql);
}

function export_cp_patient_save_patient_note($mssql, $patient, $live = false)
{
    $sql = "NOT IMPLEMENTED";

    echo "
  live:$live $sql";

    //$mssql->run("$sql");
}

function upsert_patient_cp($mssql, $sql)
{
    //echo "
    //$sql";

    return $mssql->run("$sql");
}

//EXEC SirumWeb_AddUpdatePatHomePhone only inserts new phone numbers
//6 is Phone1 and 9 is Phone2
function delete_cp_phone($mssql, $patient_id_cp, $phone_type_cn)
{
    return upsert_patient_cp($mssql, "
    UPDATE ph
    SET area_code = NULL, phone_no = NULL
    FROM cppat_phone pp
    JOIN csphone ph ON pp.phone_id = ph.phone_id
    WHERE pp.pat_id = $patient_id_cp AND pp.phone_type_cn = $phone_type_cn
  ");
}
