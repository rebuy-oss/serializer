<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class RecursionModel
{
    #[Serializer\Type('string')]
    public ?string $property = null;

    #[Serializer\MaxDepth(2)]
    #[Serializer\Type(self::class)]
    public ?RecursionModel $recursion = null;
}
