<?php


/**
 * Update Carepoint patients  I'm not sure why we do this, but we do
 * @param  Mssql_Cp  $mssql   The connection to the database
 * @param  array     $patient A bunch of patient data
 * @param  boolean   $live    (Optional) (Deprectated) No longer in use
 * @return void
 */
function export_cp_patient_save_medications_other($mssql, $patient, $live = false)
{
    $medications_other = escape_db_values($patient['medications_other']);

    $sql = "UPDATE cppat
            SET cmt =
              SUBSTRING(cmt, 0, ISNULL(NULLIF(CHARINDEX(CHAR(10)+'___', cmt), 0), 9999))+
              CHAR(10)+'______________________________________________'+CHAR(13)+
              '$medications_other'
            WHERE pat_id = {$patient['patient_id_cp']}";

    if (ENVIRONMENT == 'PRODUCTION') {
        $mssql->run("$sql");
    }
}

/**
 * Update the patient note
 * @deprecated
 * @return void
 */
function export_cp_patient_save_patient_note($mssql, $patient, $live = false)
{
    // Intentionally Left Blank
}

/**
 * Execute the SQL on the  Carepoint Server
 *
 * @param  Mssql_Cp $mssql The Carepoint Connection
 * @param  string $sql    The SQL to executed
 *
 * @return mixed  The resultes of the execution
 */
function upsert_patient_cp($mssql, $sql)
{
    if (ENVIRONMENT == 'PRODUCTION') {
        return $mssql->run("$sql");
    }
}

/**
 * Delete a phone number from carepoint
 *
 * @param  Mssql_Cp $mssql         The carepoint databse connection
 * @param  int      $patient_id_cp The carepoint patient id
 * @param  string   $phone_type_cn the type of of phone number to delted
 *
 * @return mixed
 */
function delete_cp_phone($mssql, $patient_id_cp, $phone_type_cn)
{
    return upsert_patient_cp(
        $mssql,
        "UPDATE ph
          SET area_code = NULL,
              phone_no = NULL
          FROM cppat_phone pp
          JOIN csphone ph ON pp.phone_id = ph.phone_id
          WHERE pp.pat_id = $patient_id_cp
            AND pp.phone_type_cn = $phone_type_cn"
    );
}
