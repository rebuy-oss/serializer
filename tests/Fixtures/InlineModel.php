<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class InlineModel
{
    #[Serializer\Type('string')]
    public ?string $name = null;

    #[Serializer\Inline]
    #[Serializer\Type(Nested::class)]
    public ?Nested $nested = null;

    #[Serializer\Inline]
    #[Serializer\Type('array<string, string>')]
    #[Serializer\Accessor(getter: 'getExtra', setter: 'setExtra')]
    private array $extra = [];

    public function getExtra(): array
    {
        return $this->extra;
    }

    public function setExtra(array $extra): void
    {
        $this->extra = $extra;
    }
}
