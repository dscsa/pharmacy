<?php

require_once 'keys.php';
require_once 'dbs/mysql.php';

class Mysql_Webform extends Mysql {

   function __construct(){
     parent::__construct(WC_IP, WC_USER, WC_PWD, 'goodpill');
   }

}
