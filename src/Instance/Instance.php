<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Instance;

use Nashgao\DistributedScheduler\Actor\Actor;
use Nashgao\DistributedScheduler\Message\Message;

/**
 * Combination of instance event and instance id makes the instance unique.
 */
class Instance implements InstanceInterface
{
    public string $instanceEvent;

    public string $instanceId;

    public string $uniqueId; // combination of instanceEvent and instanceId, unique among the system

    public string $serverId; // target server id

    public string $workerId; // target worker id

    public string $targetId; // combination of serverId and workerId

    public bool $transaction = false; // indicates it the transaction needs to be enabled

    public int $ttl = -1;

    public Actor $actor;

    public function receive(Message $message)
    {
        $this->actor->mailbox->push($message);
    }

    public function setInstanceEvent(string $instanceEvent): static
    {
        $this->instanceEvent = $instanceEvent;
        return $this;
    }

    public function setInstanceId(string $instanceId): static
    {
        $this->instanceId = $instanceId;
        return $this;
    }

    public function setActor(Actor $actor): static
    {
        $this->actor = $actor;
        return $this;
    }

    public function setTransaction(bool $transaction): static
    {
        $this->transaction = $transaction;
        return $this;
    }

    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }
}
