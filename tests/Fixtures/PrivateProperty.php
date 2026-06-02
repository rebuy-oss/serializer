<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class PrivateProperty
{
    #[Serializer\Type('string')]
    #[Serializer\Accessor(getter: 'getExtra', setter: 'setExtra')]
    protected ?string $extra = null;

    #[Serializer\Type('string')]
    #[Serializer\Accessor(getter: 'getApiString', setter: 'setApiString')]
    private ?string $apiString = null;

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setExtra(?string $extra): void
    {
        $this->extra = $extra;
    }

    public function getApiString(): ?string
    {
        return $this->apiString;
    }

    public function setApiString(string $apiString): void
    {
        $this->apiString = $apiString.'_setter';
    }
}
