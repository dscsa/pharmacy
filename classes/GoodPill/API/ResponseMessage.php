<?php

/**
 * Created by Reliese Model.
 */

namespace GoodPill\API;

use Psr\Http\Message\ResponseInterface;

/**
 * A simple class to allow a structed format to the API response message
 */
class ResponseMessage
{
    use \GoodPill\Traits\UsesArrayModelStorage;

    /**
     * Don't mind me just watching
     */
    public function __construct()
    {
        $this->properties = [
            'status',
            'desc',
            'data'
        ];

        $this->required = ['status'];
    }

    /**
     * Status Code to use for the resonse
     * @var int
     */
    protected $status_code = 200;

    /**
     * Make sure the status_code is set as an int
     * @param int $status_code The HTTP status code
     */
    public function setStatusCode(int $status_code)
    {
        $this->status_code = $status_code;
    }

    /**
     * Make sure the status is either success or failure
     * @param string $status The status to use
     */
    public function setStatus(string $status)
    {
        $status = strtolower($status);

        if (!in_array($status, ['success', 'failure'])) {
            throw new \Exception('Status can only be success or failure');
        }

        $this->stored_data['status'] = $status;
    }

    /**
     * Send the response properly formatted
     * @param  ResponseInterface $response The response objed
     * @return ResponseInterface
     */
    public function sendResponse(ResponseInterface $response)
    {
        $response->getBody()->write($this->toJSON());
        return $response->withHeader('Content-Type', 'application/json')
                    ->withStatus($this->status_code);
    }
}
