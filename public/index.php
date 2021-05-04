<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GoodPill\API\ResponseMessage;
use GoodPill\API\TokenSet;
use Slim\Factory\AppFactory;
use GoodPill\Models\GpOrder;
use Firebase\JWT\JWT;

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';
require_once 'keys.php';
require_once 'helpers/helper_constants.php';
require_once 'helpers/helper_laravel.php';
require_once 'helpers/helper_calendar.php';
require_once 'helpers/helper_changes.php';
require_once 'helpers/helper_log.php';

$app = AppFactory::create();

// Token Middleware
// NOTE This code is left here intentinoally.  It is fully functional, and just needs to be
// NOTE uncommented when we are ready to swith to tokens
$app->add(
    new Tuupola\Middleware\JwtAuthentication([
        "path" => "/",
        "ignore" => ["/auth"],
        "secure" => false,
        "secret" => JWT_PUB,
        "algorithm" => ["RS256"],
        'error' => function ($response, $arguments) {
            $message = new ResponseMessage();
            $message->status = 'failure';
            $message->status_code = 401;
            return $message->sendResponse($response);
        }
    ])
);

//BasicAuth Middleware
$app->add(new Tuupola\Middleware\HttpBasicAuthentication([
    "path" => "/auth",
    "ignore" => "/auth/renew",
    "realm" => "Protected",
    "users" => API_USERS,
    'error' => function ($response, $arguments) {
        $message = new ResponseMessage();
        $message->status = 'failure';
        $message->status_code = 401;
        return $message->sendResponse($response);
    }
]));

$app->addBodyParsingMiddleware();

$app->post(
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

        $request_data = (object) $request->getParsedBody();

        $order->markShipped($request_data->ship_date, $request_data->tracking_number);

        $message->status = 'success';
        return $message->sendResponse($response);
    }
);

$app->post(
    '/order/{invoice_number}/delivered',
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
        if ($order->isDelivered()) {
            $message->status = 'failure';
            $message->desc   = 'Order already marked as delivered';
            $message->status_code = 400;
            return $message->sendResponse($response);
        }

        $request_data = (object) $request->getParsedBody();

        $order->markDelivered($request_data->delivered_date, $request_data->tracking_number);

        $message->status = 'success';
        return $message->sendResponse($response);
    }
);

$app->post(
    '/auth',
    function (Request $request, Response $response, $args) {
        $message = new ResponseMessage();
        $message->status = 'success';

        // Validate data and generate a secure token
        $message->data = TokenSet::generate(['vendor'=>'stratosphere']);
        return $message->sendResponse($response);
    }
);

$app->post(
    '/auth/refresh',
    function (Request $request, Response $response, $args) {
        $message = new ResponseMessage();

        $token = $request->getHeader("Authorization");
        $token = substr(array_pop($token), 7);
        $decoded = JWT::decode($token, JWT_PUB, ['RS256']);

        if (@$token['refresh']) {
            $message->status = 'success';
            $message->data = TokenSet::generate(['vendor'=>'stratosphere']);
            return $message->sendResponse($response);
        }

        $message->status = 'failure';
        $message->status_code = 401;
        return $message->sendResponse($response);
    }
);

$app->run();
