<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ExcludeIfConstructorArguments
{
    public function __construct(
        #[Serializer\Exclude(if: 'object === null')]
        #[Serializer\Type('string')]
        public string $excluded = 'default',
        #[Serializer\Type(ExcludeIfDeserializationChild::class)]
        public ?ExcludeIfDeserializationChild $child = null,
    ) {
    }
}
