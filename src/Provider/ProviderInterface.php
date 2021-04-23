<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Provider;

/**
 * @property string $concat
 */
interface ProviderInterface
{
    public function encode(string $id, string $eventType = 'default'): string;

    public function decode(string $key): array;

    public function isLocal(string $serverWorkerIdKey): bool;

    public function inProcess(string $serverWorkerIdKey): bool;

    public function getServerWorkerIdKey(): string;

    public function getServerId(string $serverWorkerIdKey): string;

    public function getWorkerId(string $serverWorkerIdKey): int;
}
