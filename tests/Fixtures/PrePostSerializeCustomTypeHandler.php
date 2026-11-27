<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use Liip\Serializer\SerializerHandlerInterface;

class PrePostSerializeCustomTypeHandler implements SerializerHandlerInterface
{
    public function generateSerializeExpression(string $className, string $modelPath): string
    {
        return $modelPath.'->getValue()';
    }

    public function getSerializationClasses(): array
    {
        return [PrePostSerializeCustomType::class];
    }
}
