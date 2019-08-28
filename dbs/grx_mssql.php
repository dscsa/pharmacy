<?php

require './keys'
require './mssql.php'

class Grx_Mssql extends Mssql {

   function __construct(){
     parent::__construct(GRX_IP, GRX_USER, GRX_PWD, 'cph');
   }

}
