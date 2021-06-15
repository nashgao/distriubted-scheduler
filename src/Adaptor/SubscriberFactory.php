<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Adaptor;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Redis\Redis;
use Mix\Redis\Subscribe\Subscriber;

class SubscriberFactory
{
    public function __invoke(ContainerInterface $container): ?Subscriber
    {
        if (! class_exists(Subscriber::class)) {
            return null;
        }
        $redis = $container->get(Redis::class);
        $host = $redis->getHost();
        $port = $redis->getPort();
        $pass = $redis->getAuth();

        try {
            $sub = new Subscriber($host, $port, $pass ?? '', 5);
            defer(function () use ($sub) {
                $sub->close();
            });
            return $sub;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
