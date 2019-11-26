<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\HttpMessage\Server;

use Closure;
use Hyperf\HttpMessage\Base\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class ResponseFactory extends ObjectPool implements ResponseFactoryInterface
{
     /**
     * Create a new response.
     *
     * @param int $code The HTTP status code. Defaults to 200.
     * @param string $reasonPhrase The reason phrase to associate with the status code
     *     in the generated response. If none is provided, implementations MAY use
     *     the defaults as suggested in the HTTP specification.
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
    	$response = $this->get();
        $addProperty = Closure::bind(function (Response &$response) use ($code, $reasonPhrase) {
            $response->statusCode = $code;
            $response->reasonPhrase = $reasonPhrase;
            return $response;
        }, null, Response::class);
        return $addProperty($response);
    }

    protected function create(){
        return new Response();
    }
}