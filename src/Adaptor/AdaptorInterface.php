<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Adaptor;

use Hyperf\Redis\Redis;

interface AdaptorInterface
{
    public function exists(string $key): bool;

    public function get(string $key): ?string;

    public function set(string $key, string $serverWorkerIdKey, int $ttl = -1): bool;

    public function delete(string $key): bool;

    public function publish(string $message, string $channel = null);

    public function deleteAll(string $serverId = null);

    /** @deprecated  */
    public function destroyAll(string $serverId = null);

    public function setExpire(int $ttl = -1);

    public function getAdaptor(): Redis;
}
