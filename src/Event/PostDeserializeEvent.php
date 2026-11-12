<?php

declare(strict_types=1);

namespace Liip\Serializer\Event;

use Liip\Serializer\Context;

final class PostDeserializeEvent extends SerializerEvent
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        private mixed $object,
        private readonly array $data,
        ?Context $context,
        ?string $format,
    ) {
        parent::__construct($context, $format);
    }

    public function getObject(): mixed
    {
        return $this->object;
    }

    public function setObject(mixed $object): void
    {
        $this->object = $object;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }
}
