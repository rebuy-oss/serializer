<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

final class SerializerConfig
{
    public static function configure(string $cacheDirectory): SerializerConfigBuilder
    {
        return new SerializerConfigBuilder($cacheDirectory);
    }
}
