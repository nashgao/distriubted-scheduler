<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Utils\ApplicationContext;
use Nashgao\DistributedScheduler\Adaptor\AdaptorInterface;
use Nashgao\DistributedScheduler\Adaptor\RedisAdaptor;
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
        ];
    }

    /**
     * @param MainWorkerStart|object $event
     */
    public function process(object $event)
    {
        $container = ApplicationContext::getContainer();
        $enable = $container->get(ConfigInterface::class)->get('distributed_scheduler.enable') ?? true;
        if ($enable) {
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
}
