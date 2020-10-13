<?php

namespace Sirum\Notifications;

use Sirum\Storage\Goodpill;

require_once 'dbs/mysql_wc.php';

class Notification {

  protected $type = 'unkonwn';

  protected $gpdb;

  public function __construct($hash = null) {
    $this->$gpdb = Goodpill::getConnection();
  }

  public function load($hash, $token) {

  }

  public function create() {

  }

  public function hasSent() {


  }

  public function increment() {


  }
}
