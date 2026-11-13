<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

#[Serializer\Discriminator(field: 'type', map: ['visible' => ExcludeIfDiscriminatorVisible::class, 'hidden' => ExcludeIfDiscriminatorHidden::class])]
abstract class ExcludeIfDiscriminator
{
    #[Serializer\Type('string')]
    public string $name = '';
}
