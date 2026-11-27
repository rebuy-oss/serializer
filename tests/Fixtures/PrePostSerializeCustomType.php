<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class PrePostSerializeCustomType
{
    #[Serializer\Exclude]
    public array $calls = [];

    public function getValue(): string
    {
        $this->calls[] = 'handler';

        return 'value';
    }

    #[Serializer\PreSerialize]
    public function preSerialize(): void
    {
        $this->calls[] = 'preSerialize';
    }

    #[Serializer\PostSerialize]
    public function postSerialize(): void
    {
        $this->calls[] = 'postSerialize';
    }
}
