<?php

declare(strict_types=1);

namespace Liip\Serializer\Path;

abstract class AbstractEntry implements \Stringable
{
    public function __construct(private readonly string $path)
    {
    }

    abstract public function __toString(): string;

    public function getPath(): string
    {
        return $this->path;
    }
}
