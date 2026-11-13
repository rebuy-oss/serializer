<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ExcludeIfDiscriminatorParent
{
    #[Serializer\Type(ExcludeIfDiscriminator::class)]
    #[Serializer\Accessor(getter: 'getChild', setter: 'setChild')]
    private ?ExcludeIfDiscriminator $child = null;

    public function getChild(): ?ExcludeIfDiscriminator
    {
        return $this->child;
    }

    public function setChild(?ExcludeIfDiscriminator $child): void
    {
        $this->child = $child;
    }
}
