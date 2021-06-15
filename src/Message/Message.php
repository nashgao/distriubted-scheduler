<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Message;

class Message
{
    public ?string $serverId;

    public ?string $workerId;

    // key is encoded version of instance event and instance id (unique id in the instance)
    public ?string $key;

    public ?MessageBody $body;

    public ?string $schedulerClass;

    public int $ttl = -1;

    public function __construct(
        string $serverId = null,
        string $workerId = null,
        string $key = null,
        MessageBody $body = null,
        string $schedulerClass = null
    ) {
        $this->serverId = $serverId ?? null;
        $this->workerId = $workerId ?? null;
        $this->key = $key ?? null;
        $this->body = $body ?? null;
        $this->schedulerClass = $schedulerClass ?? null;
    }

    public function setServerId(string $serverId): Message
    {
        $this->serverId = $serverId;
        return $this;
    }

    public function setWorkerId(string $workerId): Message
    {
        $this->workerId = $workerId;
        return $this;
    }

    public function setKey(string $key): Message
    {
        $this->key = $key;
        return $this;
    }

    public function setBody(MessageBody $body): Message
    {
        $this->body = $body;
        return $this;
    }

    public function setSchedulerClass(?string $schedulerClass): Message
    {
        $this->schedulerClass = $schedulerClass;
        return $this;
    }

    public function setTtl(int $ttl): Message
    {
        $this->ttl = $ttl;
        return $this;
    }
}
