<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

#[Serializer\Exclude(if: 'object !== null and object.hidden')]
class ExcludeIfChild
{
    #[Serializer\Exclude]
    public bool $hidden = false;

    #[Serializer\Type('string')]
    public string $name = '';

    public static function create(string $name, bool $hidden = false): self
    {
        $child = new self();
        $child->name = $name;
        $child->hidden = $hidden;

        return $child;
    }
}
