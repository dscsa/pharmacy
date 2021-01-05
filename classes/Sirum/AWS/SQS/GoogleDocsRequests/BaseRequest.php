<?php

namespace  Sirum\AWS\SQS\GoogleDocsRequests;

use Sirum\AWS\SQS\Request;

/**
 * Base level class for all Google Doc requests
 */
class BaseRequest extends Request
{
    static public function factory($request)
    {

        $request = json_decode($request);

        if (!isset($request->type)) {
            throw \Exception('Type is missing from message request');
        }

        $type_name  = ucfirst(strtolower($request->type));
        $class_name = "Sirum\\AWS\\SQS\\GoogleDocsRequests\\{$type_name}";
        $request    = new $class_name(json_encode($request));

        return $request;
    }

    public function __construct($json_request = null)
    {
        $this->type = get_class($this);
        parent::__construct($json_request);
    }
}
