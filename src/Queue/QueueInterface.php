<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Queue;

use Nashgao\DistributedScheduler\Message\Message;

interface QueueInterface
{
    public function subscribe();

    public function publish(Message $message, string $channel = null): bool;
}
