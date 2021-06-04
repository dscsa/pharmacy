<?php
namespace GoodPill\Api;

use GoodPill\Logging\GPLog;
/**
 *
 */
class RouteLogger
{
    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request PSR7 request.
     * @param  \Psr\Http\Message\ResponseInterface      $handler PSR7 response.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $handler)
    {
        GPLog::debug(
            'Proccessing Request for:',
            [
                'attributes' => $request->getAttributes(),
                'body'       => $request->getParsedBody(),
                'method'     => $request->getMethod(),
                'path'       => $request->getUri()->getPath(),
                'query'      => $request->getUri()->getQuery()
            ]
        );

        return $handler->handle($request);
    }
}
