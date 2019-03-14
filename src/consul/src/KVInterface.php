<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://hyperf.org
 * @document https://wiki.hyperf.org
 * @contact  group@hyperf.org
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Consul;

interface KVInterface
{
    public function get($key, array $options = []): ConsulResponse;

    public function put($key, $value, array $options = []): ConsulResponse;

    public function delete($key, array $options = []): ConsulResponse;
}