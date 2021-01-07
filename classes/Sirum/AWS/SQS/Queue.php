<?php

namespace  Sirum\AWS\SQS;

use Aws\Sqs\SqsClient;
use Sirum\AWS\SQS\Request;

/**
 * Class for access Amazon Simple Queuing Service
 */
class Queue
{

    /**
     * The AWS Key
     * @var string
     */
    protected $aws_key;

    /**
     * The AWS Secret
     * @var string
     */
    protected $aws_secret;

    /**
     * The name of the queue to use
     * @var string
     */
    protected $queue_name;

    /**
     * The URL to use to access teh queue
     * @var string
     */
    protected $queue_url;

    /**
     * The SQS Client
     * @var AWs\Sqs\SqsClient
     */
    private $sqs_client;

    /**
     * Array of the default values for receive reqests
     */
    protected $default_receive_params = array('MaxNumberOfMessages' => 10,
                                              'WaitTimeSeconds'     => 20);

    /**
     * OU812!
     *
     * @param      string     $queue_name  The queue name
     * @param      string     $aws_key     The aws key
     * @param      string     $aws_secret  The aws secret
     *
     * @throws     Exception  You have to have AWS keys if you are going to access AWS
     *
     * @return     void
     */
    public function __construct($queue_name, $aws_key = null, $aws_secret = null)
    {
        if (is_null($aws_key)) {
            //Try to get the keys from constants instead
            if (defined('AWS_KEY') && defined('AWS_SECRET')) {
                $this->aws_key    = AWS_KEY;
                $this->aws_secret = AWS_SECRET;
            } else {
                throw new \Exception("You must pass AWS Key and Secret");
            }
        } else {
            $this->aws_key    = $aws_key;
            $this->aws_secret = $aws_secret;
        }

        $this->sqs_client = SqsClient::factory([
            'region'  => AWS_REGION,
            'key'     => $this->aws_key,
            'secret'  => $this->aws_secret,
            'version' => '2012-11-05']
        );

        $this->setQueueName($queue_name);
    }

    /**
     * Set the queue name and fetch the correect URL from AWS
     *
     * @param      string $queue_name The name of the queue
     *
     * @return     void
     */
    public function setQueueName($queue_name)
    {
        $this->queue_name = $queue_name;
        $this->getQueueUrl();
    }

    /**
     * Delete one or more messages
     *
     * @param  array|string $receipt_handles   A single RecieptHandle or an array of Reciept handles
     *
     * @return \AWS\Sqs\SqsResults|null If a single message is deleted, results are empty.  If multiple
     *                                  deletes are sent, results will be an array of success and failures.
     */
    public function delete($receipt_handles)
    {
        if (is_array($receipt_handles)) {
            $results = $this->sqs_client->deleteMessageBatch(
                [
                    'QueueUrl' => $this->queue_url,
                    'Entries'  => $receipt_handles
                ]
            );
        } else {
            $results = $this->sqs_client->deleteMessage(
                [
                    'QueueUrl' 	   => $this->queue_url,
                    'ReceiptHandle'  => $receipt_handles
                ]
            );
        }

        return $results;
    }


    /**
     *
     * Recieve messages from AWS SQS
     *
     * @param  array $params This can be an array of params as accepted by AWS.  The
     *      array will be merged with the default values as defined by this class
     *
     * @return \AWS\Sqs\SqsResults
     */
    public function receive($params = array())
    {
        $sqs_params = $this->default_receive_params;
        $sqs_params['QueueUrl'] = $this->queue_url;
        $sqs_params = array_merge($sqs_params, $params);

        return $this->sqs_client->receiveMessage($sqs_params);
    }

    /**
     *
     * Send a message or multiple messages to AWS SQS
     *
     * @param  Request $message       Either a single message or an array of messages
     * @param  int     $delay    Delay to send (in seconds)
     *
     * @return \AWS\Sqs\SqsResults
     */
    public function send(Request $message, $delay = null)
    {
        $sqs_message             = $message->toSQS();
        $sqs_message['QueueUrl'] = $this->queue_url;

        // Set a delay if we don't have one.
        if (!isset($sqs_message['DelaySeconds'])) {
            $sqs_message['DelaySeconds'] = ((!is_null($delay)) ?:0);
        }

        $results = $this->sqs_client->sendMessage($sqs_message);

        if (@$results['MessageId']) {
            $message->message_id = $results['MessageId'];
            $message->receipt_handle = $results['RecieptHandle'];
        }

        return $results;
    }

    /**
     *
     * Send a batch of messages.  We can't send more than 10, so it'll
     * need to be split down
     *
     * @param  array  $messages An array of Request objects
     * @param  int    $delay    Delay to send (in seconds)
     *
     * @return \AWS\Sqs\SqsResults
     */
    public function sendBatch($messages, $delay = 0)
    {

        $messages = array_map(
            function ($message, $delay) {
                return $message->toSQS();
            },
            $messages,
            array_fill(0, count($messages), $delay)
        );

        print_r($messages);

        $results = $this->sqs_client->sendMessageBatch(
            [
                'QueueUrl' => $this->queue_url,
                'Entries'  => $messages
            ]
        );

        print_r($results);

        return $results;
    }

    /**
     * Set the default parameters used when a message is received
     *
     * @param      $parms array (Optional) The array of params to use as the default
     *
     */
    public function setDefaultRecieveProperties($params = array())
    {
        if (!is_empty($params)) {
            $this->default_receive_params = $params;
        }
    }

    /**
     * Get the default parameters used for a receive
     */
    public function getDefaultRecieveProperties()
    {
        return $this->default_receive_params;
    }

    /**
     * Fetch the URL for the queue from AWS
     * @return     void
     *
     */
    protected function getQueueUrl()
    {
        $results  = $this->sqs_client->getQueueUrl(['QueueName' => $this->queue_name]);
        $this->queue_url  = $results->get('QueueUrl');
    }
}
