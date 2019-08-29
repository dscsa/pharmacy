<?php

require_once 'keys.php';
require_once 'dbs/mysql.php';

class Mysql_Webform extends Mysql {

   function __construct(){
     parent::__construct(WEBFORM_IP, WEBFORM_USER, WEBFORM_PWD, 'goodpill');
   }

}
