<?php

function export_cp_patient_save_medications_other($mssql, $patient, $live = false) {

  $sql = "
    UPDATE cppat
    SET cmt =
      SUBSTRING(cmt, 0, ISNULL(NULLIF(CHARINDEX(CHAR(10)+'___', cmt), 0), DATALENGTH(cmt)+1))+
      CHAR(10)+'______________________________________________'+CHAR(13)+
      '$patient[medications_other]'
    WHERE pat_id = $patient[patient_id_cp]
  ";

  echo "
  live:$live $sql";

  //$mssql->run("$sql");
}

function export_cp_patient_save_patient_note($mssql, $patient, $live = false) {

  $sql = "NOT IMPLEMENTED";

  echo "
  live:$live $sql";

  //$mssql->run("$sql");
}

function upsert_patient_cp($mssql, $sql, $live = false) {
  echo "
  live:$live $sql";

  if ($live) $mssql->run("$sql");
}
