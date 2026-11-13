<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ExcludeIfModel
{
    #[Serializer\Exclude]
    public bool $hideSecret = false;

    #[Serializer\Type('string')]
    public string $name = '';

    #[Serializer\Exclude(if: 'object !== null and object.hideSecret')]
    #[Serializer\Type('string')]
    public ?string $secret = null;

    #[Serializer\Expose(if: 'object === null')]
    #[Serializer\Type('string')]
    public ?string $deserializeOnly = null;

    #[Serializer\Exclude(if: 'object === null')]
    #[Serializer\Type('string')]
    public ?string $serializeOnly = null;

    #[Serializer\Exclude(if: 'context !== null')]
    #[Serializer\Type('string')]
    public ?string $withoutContextOnly = null;

    #[Serializer\Exclude(if: 'object !== null and object.hideSecret')]
    #[Serializer\Type('string')]
    #[Serializer\Accessor(getter: 'getVirtual', setter: 'setVirtual')]
    private ?string $virtual = null;

    #[Serializer\Type(ExcludeIfChild::class)]
    public ?ExcludeIfChild $child = null;

    /**
     * @var list<ExcludeIfChild>
     */
    #[Serializer\Type('array<'.ExcludeIfChild::class.'>')]
    public array $children = [];

    /**
     * @var array<string, ExcludeIfChild>
     */
    #[Serializer\Type('array<string, '.ExcludeIfChild::class.'>')]
    public array $childMap = [];

    public function getVirtual(): ?string
    {
        return $this->virtual;
    }

    public function setVirtual(?string $virtual): void
    {
        $this->virtual = $virtual;
    }
}
