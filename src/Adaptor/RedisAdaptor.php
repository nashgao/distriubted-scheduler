<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Adaptor;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Mix\Redis\Subscribe\Message;
use Mix\Redis\Subscribe\Subscriber;
use Nashgao\DistributedScheduler\Action\DeleteDistributedSchedulerAction;
use Nashgao\DistributedScheduler\DistributedScheduler;
use Nashgao\DistributedScheduler\Packer\PackerInterface;
use Nashgao\DistributedScheduler\Provider\ProviderInterface;
use RuntimeException;
use Swoole\Coroutine;

class RedisAdaptor implements AdaptorInterface, DistributedAdaptorInterface
{
    public Redis $redis;

    protected string $connection = 'default';

    protected int $retryInterval = 1000;

    protected string $concat = ':';

    private string $hashName = 'ds:key';

    private string $suffix;

    private bool $defaultSuffix;

    private string $redisPrefix = 'ds';

    private ProviderInterface $provider;

    private PackerInterface $packer;

    public function __construct(
        RedisFactory $redisFactory,
        ProviderInterface $provider,
        PackerInterface $packer,
        ConfigInterface $config
    ) {
        $this->redis = $redisFactory->get($this->connection);
        $this->provider = $provider;
        $this->packer = $packer;
        $suffix = $config->get('distributed_scheduler.suffix');
        if (isset($suffix) and ! empty($suffix)) {
            $this->suffix = $suffix;
        }
        $this->defaultSuffix = $config->get('distributed_scheduler.default_suffix') ?? false;
    }

    public function exists(string $key): bool
    {
        $hExists = $this->redis->hExists($this->joinFullHashName($this->hashName), $key);

        if ((is_bool($hExists) and $hExists) or $hExists instanceof \Redis) {
            return true;
        }

        return false;
    }

    public function get(string $key): ?string
    {
        $serverWorkerIdKey = $this->redis->hGet($this->joinFullHashName($this->hashName), $key);
        if (is_string($serverWorkerIdKey) or $serverWorkerIdKey instanceof \Redis) {
            return $serverWorkerIdKey;
        }

        return null;
    }

    public function set(string $key, string $serverWorkerIdKey, int $ttl = -1): bool
    {
        $hSet = $this->redis->hSet($this->joinFullHashName($this->hashName), $key, $serverWorkerIdKey);

        if ((is_numeric($hSet) and $hSet === 1) or $hSet instanceof \Redis) {
            $this->setExpire($ttl);
            return true;
        }

        return false;
    }

    public function delete(string $key): bool
    {
        $hDel = $this->redis->hDel($this->joinFullHashName($this->hashName), $key);

        if ((is_numeric($hDel) and $hDel > 0) or $hDel instanceof \Redis) {
            return true;
        }

        return false;
    }

    public function deleteAll(): bool
    {
        // retrieve all the keys
        $keys = $this->redis->hGetAll($this->joinFullHashName($this->hashName));
        if (! empty($keys)) {
            $this->redis->multi();
            /**
             * @var string $key   combination of "event#id"
             * @var string $value combination of "serverId#workerId"
             */
            foreach ($keys as $key => $value) { // function that remove event#id key from the hash
                // check if the key belongs to this server and worker
                $decoded = explode($this->provider->concat, $value);
                if (DistributedScheduler::$serverId === (string) $decoded[0] and getWorkerId() === (int) $decoded[1]) {
                    $this->redis->hDel($this->hashName, $key);
                }
            }
            $this->redis->exec();
        }

        return true;
    }

    public function destroyAll(): bool
    {
        return $this->deleteAll();
    }

    public function setExpire(int $ttl = -1)
    {
        if ($ttl !== -1) {
            $this->redis->expire($this->joinFullHashName($this->hashName), $ttl + 5); //todo: give a tolerant value of expiration
        }
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
                        /** @var Message $data */
                        $data = $channel->pop();
                        if (empty($data)) { // manually close redis or throw exception will trigger return false
                            if (! $subscriber->closed) {
                                throw new RuntimeException('Redis subscriber disconnected from Redis.');
                            }
                            break;
                        }

                        Coroutine::create(function () use ($data) {
                            $object = $this->packer->unpack($data->payload);
                            if ($object instanceof DeleteDistributedSchedulerAction) {
                                if ($this->provider->isLocal($object->serverWorkerIdKey)) {
                                    if ($this->provider->inProcess($object->serverWorkerIdKey)) {
                                        $scheduler = ApplicationContext::getContainer()->get($object->schedulerClass);
                                        if (isset($scheduler) and $scheduler instanceof DistributedScheduler) {
                                            $scheduler->unsetElement($object->key);
                                        }
                                    } else {
                                        getServer()->sendMessage($object, $this->provider->getWorkerId($object->serverWorkerIdKey));
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

    public function publish(string $message, string $channel = null): int
    {
        return $this->redis->publish($channel ?? $this->getChannelName(), $message);
    }

    public function getAdaptor(): Redis
    {
        return $this->redis;
    }

    private function joinFullHashName(string $hashName): string
    {
        if (! isset($this->suffix) and ! $this->defaultSuffix) {
            return $hashName;
        }

        if ($this->defaultSuffix) {
            return join($this->concat, [$hashName, DistributedScheduler::$serverId]);
        }

        return join($this->concat, [$hashName, $this->suffix]);
    }

    private function getChannelName(): string
    {
        return join($this->concat, [$this->redisPrefix, 'channel']);
    }
}
