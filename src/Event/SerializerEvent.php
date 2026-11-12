<?php

declare(strict_types=1);

namespace Liip\Serializer\Event;

use Liip\Serializer\Context;
use Symfony\Contracts\EventDispatcher\Event;

abstract class SerializerEvent extends Event
{
    public function __construct(
        private readonly ?Context $context,
        private readonly ?string $format,
    ) {
    }

    public function getContext(): ?Context
    {
        return $this->context;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }
}
