<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ListOfModel
{
    /**
     * @var Model[]
     *
     * @Serializer\Type("array<Tests\Liip\Serializer\Fixtures\Model>")
     */
    public $items;
}
