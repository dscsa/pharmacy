<?php

namespace Sirum\Notifications;

use Sirum\Storage\Goodpill;
use Sirum\GPModel;

class Notification extends GPModel {

  protected $type = 'unkonwn';

  public function __construct($hash = null) {
    parent::__construct();
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
