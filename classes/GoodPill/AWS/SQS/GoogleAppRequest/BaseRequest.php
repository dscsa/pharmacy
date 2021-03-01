<?php

namespace  GoodPill\AWS\SQS\GoogleAppRequest;

use GoodPill\AWS\SQS\Request;

/**
 * Base level class for all Google Doc requests
 */
class BaseRequest extends Request
{
    /**
     * Function takes an array from SQS library.
     * @var [type]
     */
    public static function factory($initialize_date)
    {

        if (is_array($initialize_date)) {
            $body = $initialize_date['Body'];
        } elseif (is_string($initialize_date)) {
            $body = $initialize_date;
        }

        $body = json_decode($body);

        if (!isset($body->type)) {
            throw \Exception('Type is missing from message body');
        }

        $type_name  = $body->type;
        $class_name = "GoodPill\\AWS\\SQS\\GoogleAppRequest\\{$type_name}";

        if (!class_exists($class_name)) {
            throw new \Exception("Could not find class {$class_name}");
        }

        $request = new $class_name($initialize_date);

        return $request;
    }

    public function __construct($request = null)
    {
        $this->type = substr(
            get_class($this),
            strlen('GoodPill\AWS\SQS\GoogleAppRequest\\')
        );

        parent::__construct($request);
    }
}
