<?php

require_once 'keys.php';
require_once 'dbs/mssql.php';

class Mssql_Cp extends Mssql {

   function __construct(){
     parent::__construct(MSSQL_CP_IP, MSSQL_CP_USER, MSSQL_CP_PWD, 'cph');
   }

}
