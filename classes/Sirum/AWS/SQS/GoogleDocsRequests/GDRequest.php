<?php

namespace  Sirum\AWS\SQS\GoogleDocRequests;


/**
 * Base level class for all Google Doc requests
 */
class GDRequest extends Sirum\AWS\SQS\Request
{
    static public factory($request) {
        $request = json_decode($request);

        if (!isset($request->type)) {
            throw Exception('Type is missing from message request');
        }

        $class_name = "Sirum\\AWS\\SQS\\GoogleDocRequests\\{$request->type}";
        echo $class_name;

        $request = new $class_name(json_encode($request));

        print_r($request);

        return $request;
    }
}
