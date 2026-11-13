<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ExcludeIfDiscriminatorModel
{
    /**
     * @var list<ExcludeIfDiscriminator>
     */
    #[Serializer\Type('array<'.ExcludeIfDiscriminator::class.'>')]
    public array $items = [];

    /**
     * @var list<ExcludeIfDiscriminatorParent>
     */
    #[Serializer\Type('array<'.ExcludeIfDiscriminatorParent::class.'>')]
    public array $parents = [];
}
