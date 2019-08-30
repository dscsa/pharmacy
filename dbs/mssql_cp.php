<?php

require_once 'keys.php';
require_once 'dbs/mssql.php';

class Mssql_Cp extends Mssql {

   function __construct(){
     parent::__construct(CP_IP, CP_USER, CP_PWD, 'cph');
   }

}
