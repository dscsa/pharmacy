<?php

namespace  Sirum\AWS\SQS\GoogleAppRequest;

use Sirum\AWS\SQS\Request;

/**
 * Base level class for all Google Doc requests
 */
class BaseRequest extends Request
{
    /**
     * Function takes an array from SQS library.
     * @var [type]
     */
    static public function factory($initialize_date)
    {

        if (is_array($initialize_date)) {
            $body = $initialize_date['Body'];
        } else if (is_string($initialize_date)) {
            $body = $initialize_date;
        }

        $body = json_decode($body);

        if (!isset($body->type)) {
            throw \Exception('Type is missing from message body');
        }

        $type_name  = ucfirst(strtolower($body->type));
        $class_name = "Sirum\\AWS\\SQS\\GoogleAppRequest\\{$type_name}";
        $request    = new $class_name($initialize_date);

        return $request;
    }

    public function __construct($request = null)
    {
        $this->type = substr(
            (new \ReflectionClass($this))->getClassName(),
            strlen('Sirum\AWS\SQS\GoogleAppRequest')
        );

        var_dump($this);

        parent::__construct($request);
    }
}
