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

namespace Hyperf\HttpMessage\Uri;

use Closure;
use Hyperf\HttpMessage\Base\Response;
use Hyperf\HttpMessage\Uri\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class UriFactory extends ObjectPool implements UriFactoryInterface
{
    /**
    * Create a new URI.
    *
    * @param string $uri The URI to parse.
    *
    * @throws \InvalidArgumentException If the given URI cannot be parsed.
    */
    public function createUri(string $uri = '') : UriInterface
    {
    	$uriObject = $this->get();
        $addProperty = Closure::bind(function (Uri &$uriObject) use ($uri) {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException("Unable to parse URI: {$uri}");
            }
            $uriObject->applyParts($parts);
            return $uriObject;
        }, null, Uri::class);
        return $addProperty($uriObject);
    }

    protected function create(){
        return new Response();
    }
}