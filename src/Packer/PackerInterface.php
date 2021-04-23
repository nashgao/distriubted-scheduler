<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Packer;

interface PackerInterface
{
    public function pack($payload): string;

    public function unpack($payload);
}
