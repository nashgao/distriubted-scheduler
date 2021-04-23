<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Action;

class CheckObjectExistenceAction extends AbstractAction
{
    const REQUEST = 'request';

    const RESPONSE = 'response';

    public int $sourceWorkerId;

    public int $targetWorkerId;

    public string $key;

    public string $type = self::REQUEST;

    public bool $existence = false;

    public function setSourceWorkerId(int $sourceWorkerId): CheckObjectExistenceAction
    {
        $this->sourceWorkerId = $sourceWorkerId;
        return $this;
    }

    public function setTargetWorkerId(int $targetWorkerId): CheckObjectExistenceAction
    {
        $this->targetWorkerId = $targetWorkerId;
        return $this;
    }

    public function setKey(string $key): CheckObjectExistenceAction
    {
        $this->key = $key;
        return $this;
    }

    public function setType(string $type): CheckObjectExistenceAction
    {
        $this->type = $type;
        return $this;
    }

    public function setExistence(bool $existence): CheckObjectExistenceAction
    {
        $this->existence = $existence;
        return $this;
    }
}
