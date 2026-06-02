<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class Nested
{
    #[Serializer\Type('array<string>')]
    #[Serializer\Groups(['api'])]
    #[Serializer\Accessor(getter: 'getArray')]
    public ?array $array = null;

    public function __construct(
        #[Serializer\Type('string')]
        #[Serializer\Groups(['api'])]
        public ?string $nestedString = '',
    ) {
    }

    public function getArray(): ?array
    {
        return $this->array;
    }
}
