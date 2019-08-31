<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_v2_drugs() {

  $mysql = new Mysql_Wc();

  $context = stream_context_create([
      "http" => [
          "header" => "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD)
      ]
  ]);

  $json = file_get_contents(V2_IP.'/drug/_design/by-generic-gsns/_view/by-generic-gsns?group_level=2', false, $context);
  $json = json_decode($json, true);

  $vals = [];
  foreach($json['rows'] as $row) {
    list($drug_name, $drug_gsns) = $row['key'];
    //Enclose with ,, so we can do DRUG_GSNS LIKE '%,GSN,%' and still match first and list in list
    $drug_gsns = $drug_gsns ? "',$drug_gsns,'" : 'NULL';

    $vals[] = "('$drug_name', $drug_gsns)";
  }
  echo $obj->access_token;

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_drugs_v2');

  $mysql->run("INSERT INTO gp_drugs_v2 (drug_name, drug_gsns) VALUES ".implode(', ', $vals));
}
