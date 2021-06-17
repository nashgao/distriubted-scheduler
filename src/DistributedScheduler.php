<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler;

use Hyperf\Contract\ConfigInterface;
use Nashgao\DistributedScheduler\Action\CheckObjectExistenceAction;
use Nashgao\DistributedScheduler\Action\DeleteDistributedSchedulerAction;
use Nashgao\DistributedScheduler\Adaptor\AdaptorInterface;
use Nashgao\DistributedScheduler\Annotation\DistributedScheduler as Scheduler;
use Nashgao\DistributedScheduler\Exception\DistributedSchedulerException;
use Nashgao\DistributedScheduler\Instance\Instance;
use Nashgao\DistributedScheduler\Message\Message;
use Nashgao\DistributedScheduler\Packer\PackerInterface;
use Nashgao\DistributedScheduler\Provider\ProviderInterface;
use Nashgao\DistributedScheduler\Queue\QueueInterface;
use Swoole\Coroutine\Channel;

/**
 * @Scheduler
 */
class DistributedScheduler implements DistributedSchedulerInterface
{
    public static string $serverId;

    public int $workerNum;

    public bool $isRunning = false;

    protected array $container = [];

    protected AdaptorInterface $adaptor;

    protected ProviderInterface $provider;

    protected PackerInterface $packer;

    protected QueueInterface $queue;

    protected ConfigInterface $config;

    protected Channel $channel;

    protected bool $enable_task_worker;

    public function __construct(
        AdaptorInterface $adaptor,
        ProviderInterface $provider,
        PackerInterface $packer,
        QueueInterface $queue,
        ConfigInterface $config
    ) {
        $this->adaptor = $adaptor;
        $this->provider = $provider;
        $this->packer = $packer;
        $this->queue = $queue;
        $this->config = $config;
        $this->workerNum = $this->config->get('server.settings.worker_num');
        $this->channel = new Channel(1);
        $this->isRunning = true;
        $this->enable_task_worker = $this->config->get('distributed_scheduler.enable_task_worker') ?? false;
    }

    /**
     * @throws \Throwable
     */
    public function createInstance(Instance $instance, bool $existed = false): bool
    {
        if (! $this->isRunning) {
            return false;
        }

        throws(
            fn () => ! $this->enable_task_worker and isTaskWorker(),
            new DistributedSchedulerException('unable to create the instance in task worker when enable_task_work is set to false')
        );
        throws(
            fn () => ! isset($instance->actor),
            new DistributedSchedulerException('failed to create instance, object is not set')
        );
        throws(
            fn () => ! isset($instance->instanceEvent) or ! isset($instance->instanceId),
            new DistributedSchedulerException('instance event or instance id not set')
        );

        if (! isset($instance->uniqueId)) {
            $instance->uniqueId = $this->provider->encode($instance->instanceId, $instance->instanceEvent);
        }

        if ($existed or $this->existsInstance($instance, true)) {
            return true;
        }

        if ($instance->transaction) {
            $this->adaptor->getAdaptor()->watch($instance->uniqueId); // watch the unique key in the redis
            $this->adaptor->getAdaptor()->multi();
        }

        // if the instance does not exists, then create the instance and set the instance in the container
        $instance->serverId = static::$serverId;
        $instance->workerId = (string) getWorkerId();

        // todo: need to figure out the return value of the function when the execution of the transaction fails
        $set = $this->adaptor->set($instance->uniqueId, $this->provider->getServerWorkerIdKey());

        if ($instance->transaction) {
            $this->adaptor->getAdaptor()->exec();
        }

        if (! $set) {
            return false;
        }

        if ($instance->ttl > 0) {
            $this->adaptor->setExpire($instance->ttl);
        }

        $this->container[$instance->uniqueId] = $instance;
        return true;
    }

    public function existsInstance(Instance $instance, bool $internal = false): bool
    {
        if (! $internal and ! isset($instance->uniqueId)) {
            $instance->uniqueId = $this->provider->encode($instance->instanceId, $instance->instanceEvent);
        }

        // check local process
        if (array_key_exists($instance->uniqueId, $this->container)) {
            return true;
        }

        // check local server
        for ($counter = 0; $counter < $this->workerNum; ++$counter) {
            if ($counter === getWorkerId()) {
                continue;
            }

            getServer()->sendMessage($this->createCheckExistenceAction($instance->uniqueId, getWorkerId(), $counter), $counter);

            /** @var CheckObjectExistenceAction $action */
            $action = $this->getChannel()->pop();
            if ($action->existence === true) {
                return true;
            }
        }

        $serverWorkerIdKey = $this->adaptor->get($instance->uniqueId);

        if (! isset($serverWorkerIdKey)) {
            return false;
        }

        return true;
    }

    /**
     * based on the eventKey and eventId, find the serverId and workerId of the process that stores the instance.
     * @return array[string,string]|null
     */
    public function findInstance(Instance $instance): ?array
    {
        if (! isset($instance->uniqueId)) {
            $instance->uniqueId = $this->provider->encode($instance->instanceId, $instance->instanceEvent);
        }

        // check local process
        if (array_key_exists($instance->uniqueId, $this->container)) {
            return [static::$serverId, (string) getWorkerId()];
        }
        // check local server
        for ($counter = 0; $counter < $this->workerNum; ++$counter) {
            if ($counter === getWorkerId()) {
                continue;
            }

            getServer()->sendMessage($this->createCheckExistenceAction($instance->uniqueId, getWorkerId(), $counter), $counter);

            /** @var CheckObjectExistenceAction $action */
            $action = $this->getChannel()->pop();
            if ($action->existence === true) {
                return [static::$serverId, (string) $counter];
            }
        }

        $serverWorkerIdKey = $this->adaptor->get($instance->uniqueId);

        if (isset($serverWorkerIdKey)) {
            return [$this->provider->getServerId($serverWorkerIdKey), (string) $this->provider->getWorkerId($serverWorkerIdKey)];
        }

        return [null, null];
    }

    /**
     * Actively Delete the instance.
     */
    public function deleteInstance(Instance $instance): bool
    {
        if (! $this->isRunning) {
            return false;
        }

        if (! $this->existsInstance($instance)) {
            return false;
        }

        if (! isset($instance->uniqueId)) {
            $instance->uniqueId = $this->provider->encode($instance->instanceId, $instance->instanceEvent);
        }

        /** @var string $serverWorkerIdKey */
        $serverWorkerIdKey = $this->adaptor->get($instance->uniqueId);

        if ($this->provider->isLocal($serverWorkerIdKey)) {
            if ($this->provider->inProcess($serverWorkerIdKey)) { // if the instance is in this process
                $this->unsetElement($instance->uniqueId);
            } else { // if the instance is not in this process but same server
                getServer()->sendMessage(
                    $this->createDeleteInstanceAction($instance->uniqueId, $serverWorkerIdKey),
                    $this->provider->getWorkerId($serverWorkerIdKey)
                );
            }
        } else { // if it's not in this server, publish the message and the other servers will subscribe to this
            $this->adaptor->publish(
                $this->packer->pack(
                    $this->createDeleteInstanceAction($instance->uniqueId, $serverWorkerIdKey)
                )
            );
        }

        $this->adaptor->delete($instance->uniqueId);
        return true;
    }

    /**
     * send message to corresponding instance.
     */
    public function sendTo(Message $message): bool
    {
        if (! $this->isRunning) {
            return false;
        }

        if (isset($message->serverId) and isset($message->workerId)) {
            [$serverId, $workerId] = [$message->serverId, $message->workerId];
        } else {
            // if the server id and worker id are not set, the find them
            [$eventKey, $eventId] = $this->provider->decode($message->key);
            [$serverId, $workerId] = $this->findInstance(make(Instance::class)->setInstanceEvent($eventKey)->setInstanceId($eventId));
        }

        if (! isset($serverId) or ! isset($workerId)) {
            return false;
        }

        // if it's in the same server
        if ($serverId === static::$serverId) {
            $result = (int) $workerId === getWorkerId()
                // if it's the same process, then directly push message to the mail box
                ? (function () use ($message) {
                    if (isset($this->container[$message->key])) {
                        $this->container[$message->key]->actor->mailbox->push($message);
                    }
                    return false;
                })()
                // or use ipc to send message to the corresponding process, then push to the mailbox
                : getServer()->sendMessage($message, (int) $workerId);
        } else {
            // use queue to pub and sub the message
            $result = $this->queue->publish($message->setServerId($serverId)->setWorkerId($workerId));
        }

        if ($result) {
            $this->adaptor->setExpire($message->ttl);
        }

        return $result;
    }

    public function unsetElement($key)
    {
        unset($this->container[$key]);
        isset($this->container) ? array_filter($this->container) : $this->container = [];
    }

    public function deleteAll(string $serverId = null)
    {
        return $this->adaptor->deleteAll($serverId);
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getContainer(): array
    {
        return $this->container;
    }

    public function setAdaptor(AdaptorInterface $adaptor): self
    {
        $this->adaptor = $adaptor;
        return $this;
    }

    public function setProvider(ProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function setPacker(PackerInterface $packer): self
    {
        $this->packer = $packer;
        return $this;
    }

    protected function createCheckExistenceAction(string $key, int $sourceWorkerId, int $targetWorkerId): CheckObjectExistenceAction
    {
        return make(CheckObjectExistenceAction::class)
            ->setKey($key)
            ->setSchedulerClass(get_class($this))
            ->setSourceWorkerId($sourceWorkerId)
            ->setTargetWorkerId($targetWorkerId);
    }

    protected function createDeleteInstanceAction(string $key, string $serverWorkerIdKey): DeleteDistributedSchedulerAction
    {
        return make(DeleteDistributedSchedulerAction::class)
            ->setKey($key)
            ->setSchedulerClass(get_class($this))
            ->setServerWorkerIdKey(get_class($this))
            ->setServerWorkerIdKey($serverWorkerIdKey);
    }
}
