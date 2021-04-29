<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GoodPill\API\ResponseMessage;
use Slim\Factory\AppFactory;
use GoodPill\Models\GpOrder;

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';
require_once 'helpers/helper_laravel.php';

$app = AppFactory::create();

$app->get(
    '/order/{invoice_number}/shipped',
    function (Request $request, Response $response, $args) {
        $message = new ResponseMessage();
        $order   = GpOrder::where('invoice_number', $args['invoice_number'])->first();

        // Does the order Exist
        if (!$order) {
            $message->status = 'failure';
            $message->desc   = 'Order Not Found';
            $message->status_code = 400;
            return $message->sendResponse($response);
        }

        // We can't ship an order that is already shipped
        if ($order->isShipped()) {
            $message->status = 'failure';
            $message->desc   = 'Order already marked as shipped';
            $message->status_code = 400;
            return $message->sendResponse($response);
        }

        //$order->markShipped($shipDate, $trackingNumber)

        $message->status = 'success';
        return $message->sendResponse($response);
    //$response->getBody()->write(json_encode(['success' => true]));
    //return $response->withHeader('Content-Type', 'application/json');
    //$response->getBody()->write("Hello world!");
    //return $response;
    }
);

$app->run();
