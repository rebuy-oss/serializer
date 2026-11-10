<?php

declare(strict_types=1);

namespace Liip\Serializer\Event;

use Liip\Serializer\Context;

final class PreDeserializeEvent extends SerializerEvent
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        private array $data,
        private string $type,
        ?Context $context,
        ?string $format,
    ) {
        parent::__construct($context, $format);
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param mixed[] $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
