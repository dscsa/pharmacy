<?php

namespace GoodPill\Notifications;

use GoodPill\Storage\Goodpill;
use GoodPill\GPModel;
use GoodPill\Logging\GPLog;

use \PDO;
use \Exception;

class Notification extends GPModel
{
    /**
     * List of possible properties names for this object
     * @var array
     */
    protected $fields = [
        "notification_id",
        "hash",
        "token",
        "attempted_sends",
        "details",
        "initial_send",
        "type"
     ];

    /**
     * The default type of the notification
     * @var sting
     */
    protected $type = 'unkonwn';

    /**
     * The table name to store the notifications
     * @var string
     */
    protected $table_name = "gp_notifications";

    /**
     * Load the notification.  If a notification isn't found.
     * Preload the required fields
     *
     * @param string $hash  There is a 64 character limit and
     *    this should be uniq for every notification
     *
     * @param string $token There is a 512 character limit and this
     *    should be uniq for every notification
     */
    public function __construct($hash, $token)
    {
        parent::__construct(['hash' => $hash]);

        GPLog::debug(
            'creating a Notification tracker',
            ['token' => $token, 'hash' => $hash, 'type' => $this->type]
        );

        if (!$this->isStored()) {
            $this->token = $token;
            $this->hash  = $hash;
        }
    }

    /**
     * Create a record of the notification in the database
     *
     * @return void
     *
     * @throws Exception The token and the hash fields have to
     *    be set prior to calling create
     */
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

            $pdo->bindValue(':token', $this->token, PDO::PARAM_STR);
            $pdo->bindValue(':hash', $this->hash, PDO::PARAM_STR);
            $pdo->bindValue(':details', $this->details, PDO::PARAM_STR);
            $pdo->bindValue(':type', $this->type, PDO::PARAM_STR);
            $pdo->execute();

            // Fresh load the data
            $this->load(['hash' => $this->hash]);
        }

        return $this->isStored();
    }

    /**
     * See if the notification has ever been sent.
     *
     * @return boolean Returns true if the notification is in the DB
     *    and the attempted_sends count is > 0
     */
    public function isSent()
    {
        return($this->isStored() && $this->attempted_sends > 0);
    }

    /**
     * Increase the sendcount in the database so we can
     * track items that send too much
     *
     * @return void
     */
    public function increment()
    {

        if (!$this->isStored()) {
            $this->create();
        }

        // Make sure we have something set
        $this->attempted_sends = $this->attempted_sends + 1;

        $pdo = $this->gpdb->prepare(
            "UPDATE {$this->table_name}
              SET attempted_sends = :attempted_sends
              WHERE hash = :hash"
        );

        $hash = $this->hash;

        $pdo->bindParam(':attempted_sends', $this->attempted_sends, PDO::PARAM_STR);
        $pdo->bindParam(':hash', $hash, PDO::PARAM_STR);
        $pdo->execute();
    }

    /**
     * Has this notification been stored in the database
     * @return boolean True if there is a notification_id because that is
     *    created by the datbase.
     */
    public function isStored()
    {
        return isset($this->data['notification_id']);
    }
}
