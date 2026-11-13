<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ExcludeIfDeserializationModel
{
    #[Serializer\Type('string')]
    public string $name = '';

    #[Serializer\Type(ExcludeIfDeserializationChild::class)]
    public ?ExcludeIfDeserializationChild $child = null;

    #[Serializer\Type(ExcludeIfDeserializationChild::class)]
    #[Serializer\Accessor(getter: 'getSetterChild', setter: 'setSetterChild')]
    private ?ExcludeIfDeserializationChild $setterChild = null;

    /**
     * @var list<ExcludeIfDeserializationChild>
     */
    #[Serializer\Type('array<'.ExcludeIfDeserializationChild::class.'>')]
    public array $children = [];

    public function getSetterChild(): ?ExcludeIfDeserializationChild
    {
        return $this->setterChild;
    }

    public function setSetterChild(?ExcludeIfDeserializationChild $setterChild): void
    {
        $this->setterChild = $setterChild;
    }
}
