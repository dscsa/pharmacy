<?php

function export_cp_patient_save_medications_other($mssql, $patient, $live = false) {

  $sql = "
    UPDATE cppat
    SET cmt =
      LEFT(CAST(cmt as VARCHAR(3072)), CHARINDEX(CHAR(10)+'___', cmt))+
      '______________________________________________'+CHAR(13)+
      '$patient[medications_other]'
    WHERE pat_id = $patient[patient_id_cp]
  ";

  echo "
  live:$live $sql";

  //$mssql->run("$sql");
}

function export_cp_patient_save_patient_note($mssql, $patient, $live = false) {

  $sql = "
    UPDATE cppat
    SET cmt =
      '$patient[patient_note]'+
      '______________________________________________'+CHAR(13)+
      CASE WHEN DATALENGTH(cmt) > 5 THEN RIGHT(CAST(cmt as VARCHAR(3072)), DATALENGTH(cmt)-CHARINDEX('___'+CHAR(13), cmt)-5) ELSE '' END,
    WHERE pat_id = $patient[patient_id_cp]
  ";

  echo "
  live:$live $sql";

  //$mssql->run("$sql");
}

function upsert_patient_cp($mssql, $sql, $live = false) {
  echo "
  live:$live $sql";

  if ($live) $mssql->run("$sql");
}
