<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class NonEmptyConstructor
{
    private const string FOOBAR = 'bar';

    public function __construct(
        #[Serializer\Type('string')]
        private readonly string $apiString,
        #[Serializer\Exclude]
        private readonly array $onlyArgument = ['foo', self::FOOBAR],
        #[Serializer\Type('string')]
        private readonly string $optional = 'optional',
    ) {
    }

    public function getApiString(): string
    {
        return $this->apiString;
    }

    public function getOnlyArgument(): array
    {
        return $this->onlyArgument;
    }

    public function getOptional(): string
    {
        return $this->optional;
    }
}
