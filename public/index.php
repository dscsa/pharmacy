<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GoodPill\API\ResponseMessage;
use GoodPill\API\TokenSet;
use Slim\Factory\AppFactory;
use GoodPill\Models\GpOrder;
use Firebase\JWT\JWT;
use GoodPill\Models\Utility\UtiNonce;
use GoodPill\Logging\GPLog;

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


$api_version = 'v1';

$app = AppFactory::create();

//error_reporting(E_ERROR);

// Logging all route details
$app->add(new GoodPill\API\RouteLogger());

// Token Middleware
// NOTE This code is left here intentinoally.  It is fully functional, and just needs to be
// NOTE uncommented when we are ready to swith to tokens
$app->add(
    new Tuupola\Middleware\JwtAuthentication([
        "path" => "/",
        "ignore" => ["/{$api_version}/auth"],
        "secure" => false,
        "secret" => JWT_PUB,
        "algorithm" => ["RS256"],
        'error' => function ($response, $arguments) {
            $message = new ResponseMessage();
            $message->status = 'failure';
            $message->desc = 'Failed Token';
            $message->status_code = 401;
            return $message->sendResponse($response);
        }
    ])
);

//BasicAuth Middleware
$app->add(new Tuupola\Middleware\HttpBasicAuthentication([
    "path" => "/{$api_version}/auth",
    "ignore" => "/{$api_version}/auth/refresh",
    "secure" => false,
    "realm" => "Protected",
    "users" => API_USERS,
    'error' => function ($response, $arguments) {
        $message = new ResponseMessage();
        $message->status = 'failure';
        $message->desc = 'Failed Authentication';
        $message->status_code = 401;
        return $message->sendResponse($response);
    }
]));

$app->addBodyParsingMiddleware();

$app->get(
    "/{$api_version}/order/{invoice_number}/invoice/print",
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
        $params = $request->getQueryParams();

        // Should we update the invoice?
        if (isset($params['update'])) {
            $order->updateInvoice();
        }

        $order->publishInvoice();
        $order->printInvoice();

        $message->status = 'success';
        $message->desc   = "Invoice #{$args['invoice_number']} has been queud to print";
        return $message->sendResponse($response);
    }
);

$app->post(
    "/{$api_version}/order/{invoice_number}/tracking",
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

        $request_data = (object) $request->getParsedBody();

        switch ($request_data->tracking_status['status']) {
            case 'CREATED':
            // We have to mark it shipped before we update the address in the database.
            // This will create the neccessary data in Carepoint
                $order->markShipped(
                    $request_data->tracking_status['status_date'],
                    $request_data->tracking_number
                );

                // If the address is provided, we need to update the shipping details
                if (isset($request_data->address_to)) {

                    // Update the shipping information on the package
                    if (!empty($request_data->address_to['street1'])) {
                        $order->order_address1 = $request_data->address_to['street1'];
                    }

                    if (isset($request_data->address_to['street2'])) {
                        $order->order_address2 = $request_data->address_to['street2'];
                    }

                    if (!empty($request_data->address_to['city'])) {
                        $order->order_city = $request_data->address_to['city'];
                    }
                    if (!empty($request_data->address_to['state'])) {
                        $order->order_state = $request_data->address_to['state'];
                    }
                    if (!empty($request_data->address_to['zip'])) {
                        $order->order_zip = $request_data->address_to['zip'];
                    }

                    // Will push shipping details to CarePoint
                    $order->save();
                }
                break;
            case 'UNKNOWN':
            case 'PRE_TRANSIT':
            case 'TRANSIT':
                break;
            case 'DELIVERED':
                $order->markDelivered(
                    $request_data->tracking_status['status_date'],
                    $request_data->tracking_number
                );
                break;
            case 'RETURNED':
                $order->markReturned(
                    $request_data->tracking_status['status_date'],
                    $request_data->tracking_number
                );
                break;
            case 'FAILURE':
                break;
        }

        $message->status = 'success';
        return $message->sendResponse($response);
    }
);

$app->get(
    "/{$api_version}/order/{invoice_number}/tracking",
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

        $shipment = $order->getShipUpdate();

        if ($shipment->exists) {
            $message->desc = "Order Shipped";
            $message->data = (object) [
                'invoice_number'  => $order->invoice_number,
                'tracking_number' => $shipment->TrackingNumber,
                'shipped_date'    => $shipment->ship_date,
                'delivered_date'  => $shipment->DeliveredDate
            ];
        } else {
            $message->desc = "Order not shipped";
        }

        $message->status = 'success';
        return $message->sendResponse($response);
    }
);

$app->delete(
    "/{$api_version}/order/{invoice_number}/tracking",
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

        $order->deleteShipment();

        $message->status = 'success';
        return $message->sendResponse($response);
    }
);

$app->get(
    "/{$api_version}/auth",
    function (Request $request, Response $response, $args) {
        $message = new ResponseMessage();
        $message->status = 'success';

        // Validate data and generate a secure token
        $message->data = TokenSet::generate(['vendor'=>'stratosphere']);
        return $message->sendResponse($response);
    }
);

$app->get(
    "/{$api_version}/auth/refresh",
    function (Request $request, Response $response, $args) {
        $message = new ResponseMessage();
        $token   = $request->getHeader("Authorization");
        $token   = substr(array_pop($token), 7);
        $decoded = JWT::decode($token, JWT_PUB, ['RS256']);

        $message->status = 'failure';
        $message->status_code = 401;

        if (!@$decoded->refresh) {
            $message->desc = "Invalid refresh token";
            return $message->sendResponse($response);
        }

        $nonce = UtiNonce::where('token', @$decoded->nonce)->first();

        if (is_null($nonce)) {
            $message->desc = "Invalid nonce";
            return $message->sendResponse($response);
        }

        // Delete the old nonce so it's no longer valid
        $nonce->delete();

        $message->status = 'success';
        $message->data = TokenSet::generate(['vendor'=>'stratosphere']);
        return $message->sendResponse($response);
    }
);

$app->run();
