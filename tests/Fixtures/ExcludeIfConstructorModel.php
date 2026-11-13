<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ExcludeIfConstructorModel
{
    #[Serializer\Type(ExcludeIfConstructorArguments::class)]
    public ?ExcludeIfConstructorArguments $arguments = null;
}
