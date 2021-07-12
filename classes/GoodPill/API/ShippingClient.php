<?php


namespace GoodPill\API;

use \GuzzleHttp\Client as HTTPClient;
use GoodPill\Models\GpOrder;

class ShippingClient
{

    /**
     * The base url of the shipping api
     * @var string
     */
    protected $url   = 'https://shipping.goodpill.org/api/';

    /**
     * The token to access the shipping api
     *
     * @todo This will need to be updated when we implement proper tokesn
     *
     * @var string
     */
    protected $token = 'stratosphere';

    /**
     * Create a lable with the shipping api and return the details of the new label
     * @param  GpOrder $order Should be a loaded GpOrder.
     * @return bool|object  False if we failed to creat a lable otherwise a data structure
     *      based on the shipping api
     */
    public function createLabel(GpOrder $order)
    {
        $endpoint = "order_label/{$order->invoice_number}";
        $label_details = $this->callApi($endpoint, 'POST');

        if ($label_details === false) {
            return false;
        }

        return json_decode($label_details->getContents());
    }

    /**
     * Get the Details about the shipping label
     * @param  GpOrder $order Should be a loaded GpOrder.
     * @return bool|object  False if no lable could be found, otherwise a data structure
     *      based on the shipping api
     */
    public function getLabel(GpOrder $order)
    {
        $endpoint = "order_label/{$order->invoice_number}";
        $label_details = $this->callApi($endpoint, 'GET');

        if ($label_details === false) {
            return false;
        }

        return json_decode($label_details->getContents());
    }

    /**
     * Delete/Refund a label for an order
     * @param  GpOrder $order Should be a loaded GpOrder.
     * @return bool|null  False if no lable could not be deleted, otherwise null
     */
    public function deleteLabel(GpOrder $order)
    {
        $endpoint      = "order_label/{$order->invoice_number}";
        $label_details = $this->callApi($endpoint, 'DELETE');

        if ($label_details === false) {
            return false;
        }

        return json_decode($label_details->getContents());
    }

    /**
     * Call the shipping api using Guzzle to retrieve the data
     * @param  string $endpoint The endpoint based on the base URL.
     * @param  string $verb     An appropriate HTTP verb POST, GET, DELETE, PUT.
     * @param  object $json     A json data structure conatining any additional details needed by the api.
     * @return mixed false if the api failed, otherwise the contents of the api return
     */
    protected function callApi(
        string $endpoint,
        string $verb = 'GET',
        ?array $json = null
    ) {
        $client = new HTTPClient(['base_uri' => $this->url]);

        $headers = [
            'Authorization' => $this->token,
            'Accept'        => 'application/json',
        ];

        $params = [
            'headers' => $headers
        ];

        if ($json) {
            $params['json'] = $json;
        }

        try {
            $response = $client->request($verb, $endpoint, $params);

            if ($response->getStatusCode() != 200) {
                return false;
            }

            return $response->getBody();
        } catch (\Exception $e) {
            return false;
            error_log($e->getMessage());
        }
    }
}
