<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class OptionalKeysModel
{
    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('array<string>')]
    public array $tags = [];

    #[Serializer\Exclude(if: 'true')]
    #[Serializer\Type('string')]
    public ?string $secret = null;

    #[Serializer\MaxDepth(1)]
    #[Serializer\Type(self::class)]
    public ?OptionalKeysModel $child = null;
}
