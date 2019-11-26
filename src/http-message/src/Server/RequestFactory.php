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
use Hyperf\HttpMessage\Uri\Uri;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\ObjectPool;

class RequestFactory extends ObjectPool implements ServerRequestFactoryInterface
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
     * Create a new server request.
     *
     * Note that server parameters are taken precisely as given - no parsing/processing
     * of the given values is performed. In particular, no attempt is made to
     * determine the HTTP method or URI, which must be provided explicitly.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. 
     * @param array $serverParams An array of Server API (SAPI) parameters with
     *     which to seed the generated request instance.
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        if (! $uri instanceof UriInterface) {
            $uri = $this->uriFactory->createUri($uri);
        }
        $request = $this->get();
        $addProperty = Closure::bind(function (Request &$request) use ($method, $uri, $serverParams) {
            $swooleRequest = $serverParams['swoole_request'];
            if (!($swooleRequest instanceof \Swoole\Http\Request)){
                throw new \InvalidArgumentException('Cannot find swoole request in server parameters');
            }
            $server = $swooleRequest->server;
            $request->method = $server['request_method'] ?? 'GET';
            $request->headers = $swooleRequest->header ?? [];
            $request->uri = $this->getUriFromGlobals($swooleRequest);
            $body = new SwooleStream((string) $swooleRequest->rawContent());
            $protocol = isset($server['server_protocol']) ? str_replace('HTTP/', '', $server['server_protocol']) : '1.1';
            $request = new static($method, $uri, $headers, $body, $protocol);
            $request->cookieParams = ($swooleRequest->cookie ?? []);
            $request->queryParams = ($swooleRequest->get ?? []);
            $request->serverParams = ($server ?? []);
            $request->parsedBody = self::normalizeParsedBody($swooleRequest->post ?? [], $request);
            $request->uploadedFiles = self::normalizeFiles($swooleRequest->files ?? []);
            $request->swooleRequest = $swooleRequest;
            return $request;
        }, null, Request::class);
        return $addProperty($request);
    }

    protected function parseSwooleRequest(\Swoole\Http\Request $swooleRequest): array
    {   
        $output = [];
        $server = $swooleRequest->server;
        $output['method'] = $server['request_method'] ?? 'GET';
        $output['headers']  = $swooleRequest->header ?? [];
        $output['uri'] = $this->getUriFromGlobals($swooleRequest);
        $output['body'] = new SwooleStream((string) $swooleRequest->rawContent());
        $output['protocol'] = isset($server['server_protocol']) ? str_replace('HTTP/', '', $server['server_protocol']) : '1.1';
        $output['cookieParams'] = ($swooleRequest->cookie ?? []);
        $output['queryParams'] = ($swooleRequest->get ?? []);
        $output['serverParams'] = ($server ?? []);
        $output['parsedBody'] = self::normalizeParsedBody($swooleRequest->post ?? [], $request);
        $output['uploadedFiles'] = self::normalizeFiles($swooleRequest->files ?? []);
        $output['swooleRequest'] = $swooleRequest;
    }

    /**
     * Get a Uri populated with values from $swooleRequest->server.
     * @throws \InvalidArgumentException
     * @return \Psr\Http\Message\UriInterface
     */
    protected function getUriFromGlobals(\Swoole\Http\Request $swooleRequest)
    {
        $server = $swooleRequest->server;
        $header = $swooleRequest->header;
        $uri = $this->uriFactory->createUri();
        $scheme = ! empty($server['https']) && $server['https'] !== 'off' ? 'https' : 'http';
        $port = $host = $path = $query = '';
        $hasPort = false;
        if (isset($server['http_host'])) {
            $hostHeaderParts = explode(':', $server['http_host']);
            $host = $hostHeaderParts[0];
            if (isset($hostHeaderParts[1])) {
                $hasPort = true;
                $port = $hostHeaderParts[1];
            }
        } elseif (isset($server['server_name'])) {
            $host = $server['server_name'];
        } elseif (isset($server['server_addr'])) {
            $host = $server['server_addr'];
        } elseif (isset($header['host'])) {
            $hasPort = true;
            if (\strpos($header['host'], ':')) {
                [$host, $port] = explode(':', $header['host'], 2);
            } else {
                $host = $header['host'];
            }
        }

        if (! $hasPort && isset($server['server_port'])) {
            $port = $server['server_port'];
        }

        $hasQuery = false;
        if (isset($server['request_uri'])) {
            $requestUriParts = explode('?', $server['request_uri']);
            $path = $requestUriParts[0];
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $query = $requestUriParts[1];
            }
        }

        if (! $hasQuery && isset($server['query_string'])) {
            $query = $server['query_string'];
        }

        $addProperty = Closure::bind(function (Uri &$uri) use ($scheme, $host, $port, $path, $query) {
            $uri->scheme = $scheme;
            $uri->host = $host;
            $uri->port = $port;
            $uri->path = $path;
            $uri->query = $query;
            return $uri;
        }, null, Uri::class);

        return $addProperty($uri);
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