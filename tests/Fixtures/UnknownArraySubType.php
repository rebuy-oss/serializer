<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class UnknownArraySubType
{
    #[Serializer\Type('array')]
    public ?array $unknownSubType = null;
}
