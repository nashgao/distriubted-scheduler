<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Queue;

use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Mix\Redis\Subscribe\Subscriber;
use Nashgao\DistributedScheduler\DistributedScheduler;
use Nashgao\DistributedScheduler\Instance\Instance;
use Nashgao\DistributedScheduler\Message\Message;
use Nashgao\DistributedScheduler\Packer\PackerInterface;
use RuntimeException;
use Swoole\Coroutine;

class RedisQueue implements QueueInterface
{
    protected Redis $redis;

    protected string $connection = 'default';

    protected int $retryInterval = 1000;

    protected PackerInterface $packer;

    private string $redisPrefix = 'ds:send';

    public function __construct(RedisFactory $redisFactory, PackerInterface $packer)
    {
        $this->redis = $redisFactory->get($this->connection);
        $this->packer = $packer;
    }

    public function subscribe()
    {
        Coroutine::create(function () {
            CoordinatorManager::until(Constants::WORKER_START)->yield();
            retry(PHP_INT_MAX, function () {
                try {
                    $subscriber = make(Subscriber::class);
                    $subscriber->subscribe($this->getChannelName());
                    $channel = $subscriber->channel();

                    Coroutine::create(function () use ($subscriber) {
                        CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
                        $subscriber->close();
                    });

                    while (true) {
                        /** @var \Mix\Redis\Subscribe\Message $data */
                        $data = $channel->pop();
                        if (empty($data)) { // manually close redis or throw exception will trigger return false
                            if (! $subscriber->closed) {
                                throw new RuntimeException('Redis subscriber disconnected from Redis.');
                            }
                            break;
                        }

                        Coroutine::create(function () use ($data) {
                            $object = $this->packer->unpack($data->payload);
                            if ($object instanceof Message) {
                                if ($object->serverId === DistributedScheduler::$serverId) { // only if it's in the same server
                                    if (getWorkerId() === (int) $object->workerId) {
                                        $scheduler = ApplicationContext::getContainer()->get($object->schedulerClass);
                                        if (isset($scheduler) and $scheduler instanceof DistributedScheduler) {
                                            /** @var Instance $instance */
                                            $instance = $scheduler->getContainer()[$object->key];
                                            $instance->receive($object);
                                        }
                                    } else {
                                        getServer()->sendMessage($object, $object->workerId);
                                    }
                                }
                            }
                        });
                    }
                } catch (\Throwable $e) {
                }
            }, $this->retryInterval);
        });
    }

    public function publish(Message $message, string $channel = null): bool
    {
        return $this->redis->publish($channel ?? $this->getChannelName(), $this->packer->pack($message)) > 0;
    }

    private function getChannelName(): string
    {
        return join(':', [$this->redisPrefix, 'channel']);
    }
}
