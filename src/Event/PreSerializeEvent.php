<?php

declare(strict_types=1);

namespace Liip\Serializer\Event;

use Liip\Serializer\Context;

final class PreSerializeEvent extends SerializerEvent
{
    public function __construct(
        private object $object,
        ?Context $context,
        ?string $format,
    ) {
        parent::__construct($context, $format);
    }

    public function getObject(): object
    {
        return $this->object;
    }

    public function setObject(object $object): void
    {
        $this->object = $object;
    }
}
