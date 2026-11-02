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

    /**
     * @var string[]
     */
    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('array<string>')]
    public array $flags = [];

    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('string')]
    public ?string $nullable = null;

    /**
     * @var string[]|Collection
     */
    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('ArrayCollection<string>')]
    public $collection;

    /**
     * @var SkipWhenEmpty[]
     */
    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('array<Tests\Liip\Serializer\Fixtures\SkipWhenEmpty>')]
    #[Serializer\MaxDepth(2)]
    public array $children = [];

    /**
     * @var string[]
     */
    #[Serializer\SkipWhenEmpty]
    #[Serializer\Type('array<string>')]
    #[Serializer\Accessor(getter: 'getTags')]
    private array $tags = [];

    public function __construct()
    {
        $this->collection = new ArrayCollection();
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string[] $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}
