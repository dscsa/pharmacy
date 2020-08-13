<?php

require_once 'keys.php';
require_once 'dbs/mysql.php';

class Mysql_Wc extends Mysql {

   function __construct(){
     parent::__construct(MYSQL_WC_IP, MYSQL_WC_USER, MYSQL_WC_PWD, 'goodpill');
   }

}
