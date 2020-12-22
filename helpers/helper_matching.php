<?php
require_once 'exports/export_wc_patients.php';

use Sirum\Logging\SirumLog;

//TODO Implement Full Matching Algorithm that's in Salesforce and CP's SP
function is_patient_match($mysql, $patient) {
  $patient_cp = find_patient($mysql, $patient);
  $patient_wc = find_patient($mysql, $patient, 'gp_patients_wc');

  if (count($patient_cp) == 1 AND count($patient_wc) == 1) {
    return [
      'patient_id_cp' => $patient_cp[0]['patient_id_cp'],
      'patient_id_wc' => $patient_wc[0]['patient_id_wc']
    ];
  }

  $alert = [
    'todo'              => "TODO Auto Delete Duplicate Patient AND Send Patient Comm of their login and password",
    'patient'           => $patient,
    'count(patient_cp)' => count($patient_cp),
    'count(patient_wc)' => count($patient_wc),
    'patient_cp'       => $patient_cp,
    'patient_wc'       => $patient_wc
  ];

  //TODO Auto Delete Duplicate Patient AND Send Comm of their login and password

  SirumLog::alert("update_patients_wc: WooCommerce PATIENT created $created[first_name] $created[last_name] $created[birth_date]", $alert);

  print_r($alert);
}

/**
 * Create the association between the wp and the cp patient
 * this will overwrite a current association if it exists
 *
 * @param  Mysql_Wc $mysql         The GP Mysql Connection
 * @param  array    $patient       The patient data
 * @param  int      $patient_id_cp The CP id for the patient
 * @return void
 */


function match_patient($mysql, $patient_id_cp, $patient_id_wc) {

  // Update the patientes table
  $sql_wc = "
    UPDATE
      gp_patients
    SET
      patient_id_wc = '{$patient_id_wc}'
    WHERE
      patient_id_cp = '{$patient_id_cp}'
  ";

  //Don't think this will ever be the case (since patient portal saves patient into CP directly)
  //but better safe then sorry
  $sql_cp = "
    UPDATE
      gp_patients
    SET
      patient_id_wc = '{$patient_id_cp}'
    WHERE
      patient_id_cp = '{$patient_id_wc}'
  ";

  $mysql->run($sql_wc);
  $mysql->run($sql_cp);

  log_notice("helper_matching: match_patient() matched patient_id_cp:$patient_id_cp with patient_id_wc:$patient_id_wc", [
    'sql_wc' => $sql_wc,
    'sql_cp' => $sql_cp
  ]);

  // Insert the patient_id_cp if it deosnt' already exist
  wc_upsert_patient_meta(
    $mysql,
    $patient_id_wc,
    'patient_id_cp',
    $patient_id_cp
  );
}

//TODO Implement Full Matching Algorithm that's in Salesforce and CP's SP
//Table can be gp_patients / gp_patients_wc / gp_patients_cp
function find_patient($mysql, $patient, $table = 'gp_patients') {
  $first_name_prefix = preg_split('/ |-/', $patient['first_name']);
  $last_name_prefix  = preg_split('/ |-/', $patient['last_name']); //Ignore first part of hypenated last names just like they are double last names
  $first_name_prefix = escape_db_values(substr(array_shift($first_name_prefix), 0, 3));
  $last_name_prefix  = escape_db_values(array_pop($last_name_prefix));

  $sql = "
    SELECT *
    FROM $table
    WHERE
      first_name LIKE '$first_name_prefix%' AND
      REPLACE(last_name, '*', '') LIKE '%$last_name_prefix' AND
      birth_date = '$patient[birth_date]'
  ";

  if ( ! $first_name_prefix OR ! $last_name_prefix OR ! $patient['birth_date']) {
    log_error('export_wc_patients: find_patient. patient has no name!', [$sql, $patient]);
    return [];
  }

  log_info('export_wc_patients: find_patient', [$sql, $patient]);

  $res = $mysql->run($sql)[0];

  //if ($res)
  //  echo "\npatient_id_cp:$patient[patient_id_cp] patient_id_wc:$patient[patient_id_wc] $patient[first_name] $patient[last_name] $patient[birth_date]\npatient_id_cp:{$res[0]['patient_id_cp']} patient_id_wc:{$res[0]['patient_id_wc']} {$res[0]['first_name']} {$res[0]['last_name']} {$res[0]['birth_date']}\nresult count:".count($res)."\n";

  return $res;
}
