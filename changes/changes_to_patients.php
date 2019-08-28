<?php

require '../webform_mysql.php';

function get_changes_patients() {
  $mysql = new Webform_Mysql();

  //Get Upserts (Updates & Inserts)
  $upserts = $mysql->run("
    SELECT staging.*
    FROM
      gp_patients_grx as staging
    LEFT JOIN gp_patients as pats ON
      pats.guardian_id <=> staging.guardian_id AND
      pats.first_name <=> staging.first_name AND
      pats.last_name <=> staging.last_name AND
      pats.birth_date <=> staging.birth_date AND
      pats.phone1 <=> staging.phone1 AND
      pats.phone2 <=> staging.phone2 AND
      pats.email <=> staging.email AND
      pats.pat_autofill <=> staging.pat_autofill AND
      --pats.user_def1 <=> staging.user_def1 AND
      --pats.user_def2 <=> staging.user_def2 AND
      --pats.user_def3 <=> staging.user_def3 AND
      --pats.user_def4 <=> staging.user_def4 AND
      pats.address1 <=> staging.address1 AND
      pats.address2 <=> staging.address2 AND
      pats.city <=> staging.city AND
      pats.state <=> staging.state AND
      pats.zip <=> staging.zip AND
      --pats.total_fills <=> staging.total_fills AND
      pats.pat_status <=> staging.pat_status AND
      pats.lang <=> staging.lang AND
      --pats.pat_add_date <=> staging.pat_add_date
    WHERE
      pats.guardian_id IS NULL
  ");

  //Get Removals
  $removals = $mysql->run("
    SELECT pats.*
    FROM
      gp_patients_grx as staging
    RIGHT JOIN gp_patients as pats ON
      pats.guardian_id <=> staging.guardian_id AND
      pats.first_name <=> staging.first_name AND
      pats.last_name <=> staging.last_name AND
      pats.birth_date <=> staging.birth_date AND
      pats.phone1 <=> staging.phone1 AND
      pats.phone2 <=> staging.phone2 AND
      pats.email <=> staging.email AND
      pats.pat_autofill <=> staging.pat_autofill AND
      --pats.user_def1 <=> staging.user_def1 AND
      --pats.user_def2 <=> staging.user_def2 AND
      --pats.user_def3 <=> staging.user_def3 AND
      --pats.user_def4 <=> staging.user_def4 AND
      pats.address1 <=> staging.address1 AND
      pats.address2 <=> staging.address2 AND
      pats.city <=> staging.city AND
      pats.state <=> staging.state AND
      pats.zip <=> staging.zip AND
      --pats.total_fills <=> staging.total_fills AND
      pats.pat_status <=> staging.pat_status AND
      pats.lang <=> staging.lang AND
      --pats.pat_add_date <=> staging.pat_add_date
    WHERE
      staging.guardian_id IS NULL
  ");

  //Do Upserts
  $mysql->run("
    INSERT INTO gp_patients (guardian_id,	first_name,	last_name,	birth_date,	phone1,	phone2,	email,	pat_autofill,	user_def1,	user_def2,	user_def3,	user_def4,	address1,	address2,	city,	state,	zip,	total_fills,	pat_status,	lang,	pat_add_date)
    SELECT guardian_id,	first_name,	last_name,	birth_date,	phone1,	phone2,	email,	pat_autofill,	user_def1,	user_def2,	user_def3,	user_def4,	address1,	address2,	city,	state,	zip,	total_fills,	pat_status,	lang,	pat_add_date
    FROM
      gp_patients_grx as staging
    RIGHT JOIN gp_patients as pats ON
      pats.guardian_id <=> staging.guardian_id AND
      pats.first_name <=> staging.first_name AND
      pats.last_name <=> staging.last_name AND
      pats.birth_date <=> staging.birth_date AND
      pats.phone1 <=> staging.phone1 AND
      pats.phone2 <=> staging.phone2 AND
      pats.email <=> staging.email AND
      pats.pat_autofill <=> staging.pat_autofill AND
      --pats.user_def1 <=> staging.user_def1 AND
      --pats.user_def2 <=> staging.user_def2 AND
      --pats.user_def3 <=> staging.user_def3 AND
      --pats.user_def4 <=> staging.user_def4 AND
      pats.address1 <=> staging.address1 AND
      pats.address2 <=> staging.address2 AND
      pats.city <=> staging.city AND
      pats.state <=> staging.state AND
      pats.zip <=> staging.zip AND
      --pats.total_fills <=> staging.total_fills AND
      pats.pat_status <=> staging.pat_status AND
      pats.lang <=> staging.lang AND
      --pats.pat_add_date <=> staging.pat_add_date
    WHERE
      staging.guardian_id IS NULL
    ON DUPLICATE KEY UPDATE
      guardian_id = staging.guardian_id,
      first_name = staging.first_name,
      last_name = staging.last_name,
      birth_date = staging.birth_date,
      phone1 = staging.phone1,
      phone2 = staging.phone2,
      email = staging.email,
      pat_autofill = staging.pat_autofill,
      user_def1 = staging.user_def1,
      user_def2 = staging.user_def2,
      user_def3 = staging.user_def3,
      user_def4 = staging.user_def4,
      address1 = staging.address1,
      address2 = staging.address2,
      city = staging.city,
      state = staging.state,
      zip = staging.zip,
      total_fills = staging.total_fills,
      pat_status = staging.pat_status,
      lang = staging.lang,
      pat_add_date = staging.pat_add_date
  ");

  //Do Removals
  $mysql->run("
    DELETE pats
    FROM gp_patients_grx as staging
    RIGHT JOIN gp_patients as pats ON
      pats.guardian_id <=> staging.guardian_id AND
      pats.first_name <=> staging.first_name AND
      pats.last_name <=> staging.last_name AND
      pats.birth_date <=> staging.birth_date AND
      pats.phone1 <=> staging.phone1 AND
      pats.phone2 <=> staging.phone2 AND
      pats.email <=> staging.email AND
      pats.pat_autofill <=> staging.pat_autofill AND
      --pats.user_def1 <=> staging.user_def1 AND
      --pats.user_def2 <=> staging.user_def2 AND
      --pats.user_def3 <=> staging.user_def3 AND
      --pats.user_def4 <=> staging.user_def4 AND
      pats.address1 <=> staging.address1 AND
      pats.address2 <=> staging.address2 AND
      pats.city <=> staging.city AND
      pats.state <=> staging.state AND
      pats.zip <=> staging.zip AND
      --pats.total_fills <=> staging.total_fills AND
      pats.pat_status <=> staging.pat_status AND
      pats.lang <=> staging.lang AND
      --pats.pat_add_date <=> staging.pat_add_date
    WHERE
      staging.guardian_id IS NULL
  ");
return ['upserts' => $upserts, 'removals' => $removals];
}
