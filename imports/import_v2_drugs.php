<?php

require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_v2_drugs() {

  $mysql = new Mysql_Wc();

  ini_set("allow_url_fopen", 1);
  $json = file_get_contents('http://52.9.6.78:5984/drug/_design/by-generic-gsns/_view/by-generic-gsns?group_level=2');
  $json = json_decode($json);

  $vals = [];
  foreach($json->rows as $row) {
    //Enclose with ,, so we can do DRUG_GSNS LIKE '%,GSN,%' and still match first and list in list
    $drug_gsns = $row->key[1] ? "',$row->key[1],'" : 'NULL';
    $vals[] = "('$row->key[0]', $drug_gsns)";
  }
  echo $obj->access_token;

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_drugs_v2');

  $mysql->run("INSERT INTO gp_drugs_v2 (drug_name, drug_gsns) VALUES ".implode(', ', $vals));
}
