<?php

require_once 'keys.php';
require_once 'dbs/mysql.php';

class Mysql_Wc extends Mysql {

   function __construct(){
     parent::__construct(DB_IP, DB_USER, DB_PWD, 'goodpill');
   }

}
