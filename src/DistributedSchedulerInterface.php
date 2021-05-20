<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler;

use Nashgao\DistributedScheduler\Instance\Instance;
use Nashgao\DistributedScheduler\Message\Message;

interface DistributedSchedulerInterface
{
    public function createInstance(Instance $instance): bool;

    public function findInstance(Instance $instance): ?array;

    public function existsInstance(Instance $instance, bool $internal):bool;

    public function deleteInstance(Instance $instance): bool;

    public function sendTo(Message $message): bool;
}
