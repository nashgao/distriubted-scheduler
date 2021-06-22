<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Actor;

use Nashgao\Finite\Machine\FiniteStateMachine;
use Nashgao\Finite\StatefulInterface;
use Swoole\Coroutine\Channel;

abstract class Actor extends FiniteStateMachine implements ActorInterface
{
    public Channel $mailbox;

    public array $children = [];

    public function __construct(StatefulInterface $object)
    {
        parent::__construct($object);
        $this->mailbox = new Channel(1);
    }

    public function receive()
    {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
