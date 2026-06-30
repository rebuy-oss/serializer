<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class DefaultValues
{
    /**
     * @Serializer\Type("array<string>")
     */
    public ?array $list = [];

    public ?Nested $nested;

    /**
     * @Serializer\Type("DateTimeImmutable")
     */
    public $createdAt;

    /**
     * @Serializer\Type("DateTimeImmutable<'Y-m-d'>")
     */
    public $updatedAt;

    /**
     * @Serializer\Type("DateTimeImmutable<'Ymd', 'UTC', ['d/m/Y', 'Ymd']>")
     */
    public $plannedFor;

    /**
     * @Serializer\Type("Tests\Liip\Serializer\Fixtures\BackedStringEnum")
     */
    public $stringEnum;
}
