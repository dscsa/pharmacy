<?php
ini_set('include_path', '/goodpill/webform');
require_once 'vendor/autoload.php';
require_once 'helpers/helper_appsscripts.php';
require_once 'helpers/helper_log.php';
require_once 'keys.php';

use GoodPill\AWS\SQS\{
    Queue,
    Request,
};

class CopiedRequest extends Request{
    /**
     * Allow any properties since we are copying known good SQS messages
     * @var boolean
     */
    public $check_property = false;

    /**
     * Allow duplicate message.  Don't check to see if this
     * message has ever been sent to SQS
     * @var boolean
     */
    public $allow_duplicate = true;
}

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};

$from_queue = new Queue('gdoc_requests_dlq.fifo');
$to_queue = new Queue('gdoc_requests.fifo');

$types = [];
// Keep doing this until there are no messages left
do {
    $results  = $from_queue->receive([
        'MaxNumberOfMessages' => 10,
        'AttributeNames' => [
            'MessageGroupId',
            'SequenceNumber'
        ]
    ]);

    $messages = $results->get('Messages');

    if (is_array($messages) && count($messages) > 0) {
        $request_to_move = [];
        foreach ($messages as $message) {
            $request = new CopiedRequest($message);
            $request_to_move[] = $request;
            $types[$request->method] = (isset($types[$request->method])) ? ++$types[$request->method] : 1;
        }

        $to_queue->sendBatch($request_to_move);
        $from_queue->deleteBatch($request_to_move);
    }
} while (is_array($messages) && count($messages) > 0);

if (!empty($types)) {
    printf(
        "A total of %d messages were moved from %s to %s\n",
        array_sum($types),
        $from_queue->getQueueName(),
        $to_queue->getQueueName()
    );

    var_dump($types);
} else {
    echo "No messages were moved\n";
}
