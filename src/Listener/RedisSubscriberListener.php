<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Utils\ApplicationContext;
use Nashgao\DistributedScheduler\Adaptor\AdaptorInterface;
use Nashgao\DistributedScheduler\Adaptor\RedisAdaptor;
use Nashgao\DistributedScheduler\Event\SubscribeEvent;
use Nashgao\DistributedScheduler\Queue\QueueInterface;
use Nashgao\DistributedScheduler\Queue\RedisQueue;

class RedisSubscriberListener implements ListenerInterface
{
    /**
     * @return string[]
     */
    public function listen(): array
    {
        return [
            MainWorkerStart::class,
            SubscribeEvent::class,
        ];
    }

    /**
     * @param MainWorkerStart|object $event
     */
    public function process(object $event)
    {
        $container = ApplicationContext::getContainer();
        $adaptor = $container->get(AdaptorInterface::class);
        if ($adaptor instanceof RedisAdaptor) {
            $adaptor->subscribe();
        }

        $queue = $container->get(QueueInterface::class);
        if ($queue instanceof RedisQueue) {
            $queue->subscribe();
        }
    }
}
