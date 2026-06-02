<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class Versions
{
    #[Serializer\Type('string')]
    #[Serializer\Until('2')]
    public ?string $old = null;

    #[Serializer\Type('string')]
    #[Serializer\Until('2')]
    public ?string $changed = null;

    #[Serializer\Type('string')]
    #[Serializer\Since('3')]
    public ?string $new = null;

    #[Serializer\Type('string')]
    #[Serializer\Since('3')]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('changed')]
    public function getChangedInV3(): string
    {
        return mb_strtoupper((string) $this->changed);
    }
}
