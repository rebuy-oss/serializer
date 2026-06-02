<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as JMS;

#[JMS\Discriminator(field: 'type', map: ['first' => DiscriminatorFirstChild::class, 'second' => DiscriminatorSecondChild::class])]
abstract class Discriminator
{
}
