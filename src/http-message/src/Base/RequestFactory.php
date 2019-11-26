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
use Hyperf\HttpMessage\Base\Request;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class RequestFactory extends ObjectPool implements RequestFactoryInterface
{
    /**
     * @var UriFactoryInterface
     */
    protected $uriFactory;

    public function __construct(UriFactoryInterface $uriFactory)
    {
        parent::__construct();
        $this->uriFactory = $uriFactory;
    }

    /**
     * Create a new request.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. 
     */
    public function createRequest(string $method, $uri): RequestInterface 
    {
        if (! $uri instanceof UriInterface) {
            $uri = $this->uriFactory->createUri($uri);
        }
    	$request = $this->get();
        $addProperty = Closure::bind(function (Request &$request) use ($method, $uri) {
            $request->method = strtoupper($method);
            $request->uri = $uri;
            return $request;
        }, null, Request::class);
        return $addProperty($request);
    }

    protected function create(){
        return new Request('GET', $this->uriFactory->createUri('/'));
    }
}