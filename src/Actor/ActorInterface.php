<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Actor;

interface ActorInterface
{
    public function toArray(): array;

    public function receive();

    public function getLoaderConfig(): array;
}
