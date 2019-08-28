<?php

require './keys'
require '../mysql.php'

class Webform_Mysql extends Mysql {

   function __construct(){
     parent::__construct(WEBFORM_IP, WEBFORM_USER, WEBFORM_PWD, 'goodpill');
   }

}
