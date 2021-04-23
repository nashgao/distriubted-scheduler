<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Actor;

use Finite\Event\StateMachineDispatcher;
use Finite\State\Accessor\StateAccessorInterface;
use Finite\StateMachine\StateMachine;
use Swoole\Coroutine\Channel;

class Actor extends StateMachine implements ActorInterface
{
    public Channel $mailbox;

    public array $children = [];

    public function __construct(object $object = null, StateMachineDispatcher $dispatcher = null, StateAccessorInterface $stateAccessor = null)
    {
        parent::__construct($object, $dispatcher, $stateAccessor);
        $this->mailbox = new Channel(1);
    }

    public function receive()
    {
    }

    public function getLoaderConfig(): array
    {
        return [];
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
