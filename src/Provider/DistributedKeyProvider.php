<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Provider;

use Nashgao\DistributedScheduler\DistributedScheduler;

class DistributedKeyProvider implements ProviderInterface, DistributedProviderInterface
{
    public string $concat = '#';

    public function encode(string $id, string $eventType = 'default'): string
    {
        return join($this->concat, [
            $eventType,
            $id,
        ]);
    }

    /**
     * @return array[string,string]
     */
    public function decode(string $key): array
    {
        [$serverId, $workerId] = explode($this->concat, $key);
        return [$serverId, $workerId];
    }

    public function isLocal(string $serverWorkerIdKey): bool
    {
        return explode($this->concat, $serverWorkerIdKey)[0] === DistributedScheduler::$serverId;
    }

    public function inProcess(string $serverWorkerIdKey): bool
    {
        return $this->getWorkerId($serverWorkerIdKey) === getWorkerId();
    }

    public function getServerWorkerIdKey(): string
    {
        return join($this->concat, [DistributedScheduler::$serverId, (string) getWorkerId()]);
    }

    public function getServerId(string $serverWorkerIdKey): string
    {
        return explode($this->concat, $serverWorkerIdKey)[0];
    }

    public function getWorkerId(string $serverWorkerIdKey = null): int
    {
        return (int) explode($this->concat, $serverWorkerIdKey)[1];
    }
}
