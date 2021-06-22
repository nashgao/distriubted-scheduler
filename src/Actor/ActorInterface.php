<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Actor;

use Nashgao\Finite\Config\Config;

interface ActorInterface
{
    public function toArray(): array;

    public function receive();

    public function getConfig(): Config;
}
