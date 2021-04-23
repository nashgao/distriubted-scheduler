<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Signal;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Signal\Annotation\Signal;
use Hyperf\Signal\SignalHandlerInterface;
use Hyperf\Utils\ApplicationContext;
use Nashgao\DistributedScheduler\Annotation\Scheduler;
use Nashgao\DistributedScheduler\DistributedScheduler;

/**
 * @Signal
 */
class TermSignalHandler implements SignalHandlerInterface
{
    public function listen(): array
    {
        return [
            [SignalHandlerInterface::WORKER, SIGTERM],
            [SignalHandlerInterface::WORKER, SIGKILL],
        ];
    }

    public function handle(int $signal): void
    {
        $container = ApplicationContext::getContainer();
        // get annotation classes
        $classes = AnnotationCollector::getClassesByAnnotation(Scheduler::class);
        if (! empty($classes)) {
            foreach ($classes as $class => $object) {
                $scheduler = $container->get($class);
                if (! $scheduler instanceof DistributedScheduler) {
                    continue;
                }
                $scheduler->isRunning = false;
                if (! empty($scheduler->getContainer())) {
                    $scheduler->deleteAll();
                }
            }
        }
    }
}
