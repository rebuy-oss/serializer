<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;

class SkipWhenEmpty
{
    #[Serializer\Type('string')]
    public string $name = '';

    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('array<string>')]
    public array $flags = [];

    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('string')]
    public ?string $nullable = null;

    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('ArrayCollection<string>')]
    public Collection $collection;

    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('array<Tests\Liip\Serializer\Fixtures\SkipWhenEmpty>')]
    #[Serializer\MaxDepth(2)]
    public array $children = [];

    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('array<string>')]
    #[Serializer\Accessor(getter: 'getTags')]
    private array $tags = [];

    public function __construct()
    {
        $this->collection = new ArrayCollection();
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}
