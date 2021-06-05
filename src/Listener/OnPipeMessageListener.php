<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnPipeMessage;
use Hyperf\Utils\ApplicationContext;
use Nashgao\DistributedScheduler\Action\CheckObjectExistenceAction;
use Nashgao\DistributedScheduler\Action\DeleteDistributedSchedulerAction;
use Nashgao\DistributedScheduler\DistributedScheduler;
use Nashgao\DistributedScheduler\Instance\Instance;
use Nashgao\DistributedScheduler\Message\Message;

class OnPipeMessageListener implements ListenerInterface
{
    protected ConfigInterface $config;

    protected bool $enable_task_worker;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->enable_task_worker = $this->config->get('distributed_scheduler.enable_task_worker') ?? false;
    }

    /**
     * @return string[]
     */
    public function listen(): array
    {
        return [
            OnPipeMessage::class,
        ];
    }

    /**
     * @param object|OnPipeMessage $event
     */
    public function process(object $event)
    {
        if (! $this->isEnabled()) {
            return ;
        }

        $this->existence($event);
        $this->delete($event);
        $this->sendMessage($event);
    }

    protected function isEnabled(): bool
    {
        if (! $this->enable_task_worker and isTaskWorker()) {
            return false;
        }

        return true;
    }

    protected function existence(OnPipeMessage $event)
    {
        // check if the actor exists in the container
        if ($event->data instanceof CheckObjectExistenceAction) {
            // if it's response, then push to the channel
            // make sure put the response check on top, or it will be triggered right after the request is changed to response in the wrong worker
            if ($event->data->type === $event->data::RESPONSE and $event->data->sourceWorkerId === getWorkerId()) {
                $scheduler = ApplicationContext::getContainer()->get($event->data->schedulerClass);
                if (isset($scheduler) and $scheduler instanceof DistributedScheduler) {
                    $scheduler->getChannel()->push($event->data);
                }
            }

            // if it's request, then check if the object actually exists
            if ($event->data->type === $event->data::REQUEST) {
                // set the type to response first
                $event->data->setType($event->data::RESPONSE);
                $scheduler = ApplicationContext::getContainer()->get($event->data->schedulerClass);
                if (isset($scheduler) and $scheduler instanceof DistributedScheduler) {
                    if (array_key_exists($event->data->key, $scheduler->getContainer())) {
                        $event->data->setExistence(true);
                    }
                }
                // send back to the corresponding worker
                getServer()->sendMessage($event->data, $event->data->sourceWorkerId);
            }
        }
    }

    protected function delete(OnPipeMessage $event)
    {
        // delete the actor in the container
        if ($event->data instanceof DeleteDistributedSchedulerAction) {
            $scheduler = ApplicationContext::getContainer()->get($event->data->schedulerClass);
            if (isset($scheduler) and $scheduler instanceof DistributedScheduler) {
                $scheduler->unsetElement($event->data->key);
            }
        }
    }

    protected function sendMessage(OnPipeMessage $event)
    {
        // send message to the mailbox of the corresponding actor
        if ($event->data instanceof Message) {
            if (isset($event->data->schedulerClass) and ApplicationContext::getContainer()->has($event->data->schedulerClass)) {
                $scheduler = ApplicationContext::getContainer()->get($event->data->schedulerClass);
                if (isset($scheduler) and $scheduler instanceof DistributedScheduler) {
                    if (array_key_exists($event->data->key, $scheduler->getContainer())) {
                        /** @var Instance $instance */
                        $instance = $scheduler->getContainer()[$event->data->key];
                        if ($instance instanceof Instance) {
                            $instance->actor->mailbox->push($event->data);
                        }
                    }
                }
            } else {
                throw new \Exception('invalid scheduler class');
            }
        }
    }
}
