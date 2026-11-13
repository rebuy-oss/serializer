<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

#[Serializer\Exclude(if: 'object === null')]
class ExcludeIfDiscriminatorHidden extends ExcludeIfDiscriminator
{
}
