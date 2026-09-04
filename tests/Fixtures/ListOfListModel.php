<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class ListOfListModel
{
    /**
     * @var ListModel[]
     *
     * @Serializer\Type("array<Tests\Liip\Serializer\Fixtures\ListModel>")
     */
    public $items;
}
