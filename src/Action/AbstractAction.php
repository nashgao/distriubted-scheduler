<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Action;

class AbstractAction
{
    public string $schedulerClass;

    public function setSchedulerClass(string $schedulerClass): AbstractAction
    {
        $this->schedulerClass = $schedulerClass;
        return $this;
    }
}
