<?php

declare(strict_types=1);

namespace Nashgao\DistributedScheduler\Packer;

class Serializer implements PackerInterface
{
    public function pack($payload): string
    {
        return serialize($payload);
    }

    public function unpack($payload)
    {
        return unserialize($payload);
    }
}
