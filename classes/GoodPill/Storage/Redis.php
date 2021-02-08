<?php

namespace GoodPill\Storage;

use \Predis\Client;
/**
 * Class for a RX Transfer notifcation
 */
class Redis
{
    /**
     * Store the client so we don't have a bunch of them
     * @var Predis\Client
     */
    public static $client;

    /**
     * Build it like you want it
     * @return void
     */
    public function __construct()
    {
        $this->getClient();
    }

    /**
     * Get a client and store it in a static variable
     * @return Predis\Client
     */
    protected function getClient()
    {
        if (!isset(self::$client) || !is_a(self::$client, '\Predis\Client')) {
            self::$client = new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => REDIS_HOST,
                'port'   => REDIS_PORT,
            ]);
        }

        return self::$client;
    }

    /**
     * Use the __call method to pass all requests through to the client
     * @param  string $method The method to call
     * @param  array  $args   The args passed to the function
     * @return mixed
     */
    public function __call($method, $args)
    {
        $client = $this->getClient();
        return call_user_func_array([$client, $method], $args);
    }
}
