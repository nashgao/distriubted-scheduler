<?php

declare(strict_types=1);


namespace Nashgao\Test\Stub\Job;

use Nashgao\DistributedScheduler\Instance\Instance;

class TestInstance extends Instance
{
    public int $time = 2000;

    public string $id = 'testJob';

    public string $event = 'testEvent';

    public string $timerType = 'after';

    public string $timerAttribute = 'unique';
}
