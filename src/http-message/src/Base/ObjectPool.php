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

namespace Hyperf\HttpMessage\Base;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use BadMethodCallException;
use RuntimeException;

abstract class ObjectPool
{
    /**
     * @var Channel
     */
    protected $objectPool;

    protected $type = __CLASS__;

    public function __construct(){
        $this->type = get_called_class();
        $this->objectPool = new Channel(100000);
    }

    public function get()
    {
        $context = Coroutine::getContext();
        if (!$context) {
            throw new BadMethodCallException('ObjectPool misuse: get must be used in coroutine');
        }
        $type = $this->type;
        Coroutine::defer(function () {
            $this->free();
        });
        if (isset($context[$type])) {
            return $context[$type];
        }
        if (!$this->object_pool->isEmpty()) {
            $object = $this->object_pool->pop();
        } else {
            $object = $this->create();
            if (empty($object)) {
                throw new RuntimeException('ObjectPool misuse: create object failed');
            }
        }
        $context[$type] = $object;
        return $object;
    }
    public function free()
    {
        $context = Coroutine::getContext();
        if (!$context) {
            throw new BadMethodCallException('ObjectPool misuse: free must be used in coroutine');
        }
        $type = $this->type;
        $object = $context[$type];
        $this->object_pool->push($object);
    }
    protected abstract function create();
}