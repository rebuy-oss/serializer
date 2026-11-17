<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class InlineModelWithGenericArray
{
    #[Serializer\Type('string')]
    public ?string $name = null;

    #[Serializer\Inline]
    #[Serializer\Type(Nested::class)]
    public ?Nested $nested = null;

    #[Serializer\Inline]
    #[Serializer\Type('array')]
    public array $genericArray = [];
}
