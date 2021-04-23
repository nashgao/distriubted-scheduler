<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Action;

class DeleteDistributedSchedulerAction extends AbstractAction
{
    public string $key;

    public string $serverWorkerIdKey;

    public function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    public function setServerWorkerIdKey(string $serverWorkerIdKey): self
    {
        $this->serverWorkerIdKey = $serverWorkerIdKey;
        return $this;
    }
}
