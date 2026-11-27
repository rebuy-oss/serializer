<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class PrePostSerialize
{
    #[Serializer\Exclude]
    public array $calls = [];

    public ?PrePostSerializeNested $nested = null;

    #[Serializer\VirtualProperty]
    public function getValue(): string
    {
        $this->calls[] = 'getValue';

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
