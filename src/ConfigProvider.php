<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler;

use Mix\Redis\Subscribe\Subscriber;
use Nashgao\DistributedScheduler\Adaptor\AdaptorInterface;
use Nashgao\DistributedScheduler\Adaptor\RedisAdaptor;
use Nashgao\DistributedScheduler\Adaptor\SubscriberFactory;
use Nashgao\DistributedScheduler\Listener\RedisSubscriberListener;
use Nashgao\DistributedScheduler\Listener\ServerIdListener;
use Nashgao\DistributedScheduler\Packer\PackerInterface;
use Nashgao\DistributedScheduler\Packer\Serializer;
use Nashgao\DistributedScheduler\Provider\DistributedKeyProvider;
use Nashgao\DistributedScheduler\Provider\ProviderInterface;
use Nashgao\DistributedScheduler\Queue\QueueInterface;
use Nashgao\DistributedScheduler\Queue\RedisQueue;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                DistributedSchedulerInterface::class => DistributedScheduler::class,
                Subscriber::class => SubscriberFactory::class,
                AdaptorInterface::class => RedisAdaptor::class,
                PackerInterface::class => Serializer::class,
                ProviderInterface::class => DistributedKeyProvider::class,
                QueueInterface::class => RedisQueue::class,
            ],
            'listeners' => [
                RedisSubscriberListener::class,
                ServerIdListener::class
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'description of this config file.',
                    'source' => __DIR__ . '/../publish/distributed_scheduler.php',
                    'destination' => BASE_PATH . '/config/autoload/distributed_scheduler.php',
                ]
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}
