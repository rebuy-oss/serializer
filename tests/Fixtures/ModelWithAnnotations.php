<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ModelWithAnnotations
{
    /**
     * @Serializer\Type("string")
     */
    public $name;

    /**
     * @Serializer\Type("integer")
     */
    public $age;

    /**
     * @Serializer\Type("array<int, string>")
     */
    public $properties;
}
