<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler;

use Nashgao\DistributedScheduler\Instance\Instance;

interface DistributedSchedulerInterface
{
    public function createInstance(Instance $instance): bool;
}
