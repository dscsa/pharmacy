<?php

namespace Sirum\Notifications;

use Sirum\Storage\Goodpill;
use Sirum\GPModel;

use \PDO;
use \Exception;

class Notification extends GPModel
{
    protected $field_names = [
                                "notification_id",
                                "hash",
                                "token",
                                "attempted_sends",
                                "details",
                                "initial_send",
                                "type"
                             ];

    protected $type = 'unkonwn';

    protected $table_name = "gp_notifications";

    public function __construct($hash = null)
    {
        parent::__construct();

        if (! is_null($hash)) {
            $this->load($hash);
        }
    }

    public function load($hash)
    {
        $pdo = $this->gpdb->prepare(
            "SELECT *
          FROM {$this->table_name}
          WHERE hash = :hash"
        );

        $pdo->bindParam(':hash', $hash, PDO::PARAM_STR);

        $pdo->execute();

        if ($notification = $pdo->fetch()) {
            $this->setDataArray($notification);
        }
    }

    public function create()
    {

        if (!isset($this->token) || !isset($this->hash)) {
            throw new Exception("token and hash are required fields");
        }

        if (!$this->isStored()) {
            $pdo = $this->gpdb->prepare(
                "INSERT INTO {$this->table_name}
                  (hash, token, details, type)
                  VALUES
                  (:hash, :token, :details, :type)"
            );

            $pdo->bindParam(':token', $this->token, PDO::PARAM_STR);
            $pdo->bindParam(':hash', $this->hash, PDO::PARAM_STR);
            $pdo->bindParam(':details', $this->details, PDO::PARAM_STR);
            $pdo->bindParam(':type', $this->type, PDO::PARAM_STR);
            $pdo->execute();

            // Fresh load the data
            $this->load($this->hash);
        }

        return $this->isStored();
    }


    public function hasSent()
    {
        return($this->isStored() && $this->attempted_sends > 0);
    }

    public function increment()
    {
        // Make sure we have something set
        $this->attempted_sends = $this->attempted_sends + 1;

        if ($this->isStored()) {
            $pdo = $this->gpdb->prepare(
                "UPDATE {$this->table_name}
                  SET attempted_sends = :attempted_sends
                  WHERE hash = :hash"
            );

            $pdo->bindParam(':attempted_sends', $this->attempted_sends, PDO::PARAM_STR);
            $pdo->bindParam(':hash', $this->hash, PDO::PARAM_STR);
            $pdo->execute();
        }
    }

    public function isStored()
    {
        return isset($this->data['notification_id']);
    }
}
