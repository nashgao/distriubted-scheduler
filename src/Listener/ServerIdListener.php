<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\Utils\ApplicationContext;
use Nashgao\DistributedScheduler\Adaptor\AdaptorInterface;
use Nashgao\DistributedScheduler\Adaptor\DistributedAdaptorInterface;
use Nashgao\DistributedScheduler\DistributedScheduler;
use Nashgao\DistributedScheduler\Exception\DistributedSchedulerException;
use Nashgao\DistributedScheduler\Provider\DistributedProviderInterface;
use Nashgao\DistributedScheduler\Provider\ProviderInterface;

class ServerIdListener implements ListenerInterface
{
    /**
     * @return string[]
     */
    public function listen(): array
    {
        return [
            BeforeMainServerStart::class,
        ];
    }

    /**
     * @throws DistributedSchedulerException
     */
    public function process(object $event)
    {
        $container = ApplicationContext::getContainer();

        if (
            ($container->get(AdaptorInterface::class) instanceof DistributedAdaptorInterface and ! $container->get(ProviderInterface::class) instanceof DistributedProviderInterface)
            or (! $container->get(AdaptorInterface::class) instanceof DistributedAdaptorInterface and $container->get(ProviderInterface::class) instanceof DistributedProviderInterface)
        ) {
            throw new DistributedSchedulerException('invalid adaptor or provider interface');
        }

        DistributedScheduler::$serverId = uniqid();
    }
}
