<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

enum GenerationTarget
{
    case Both;
    case Serializer;
    case Deserializer;

    public function includesSerializer(): bool
    {
        return self::Deserializer !== $this;
    }

    public function includesDeserializer(): bool
    {
        return self::Serializer !== $this;
    }
}
