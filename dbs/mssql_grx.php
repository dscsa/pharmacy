<?php

require './keys'
require './mssql.php'

class Mssql_Grx extends Mssql {

   function __construct(){
     parent::__construct(GRX_IP, GRX_USER, GRX_PWD, 'cph');
   }

}
