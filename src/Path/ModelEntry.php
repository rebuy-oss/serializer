<?php

declare(strict_types=1);

namespace Liip\Serializer\Path;

final class ModelEntry extends AbstractEntry implements \Stringable
{
    public function __construct(string $path, private bool $nullCheck = false)
    {
        parent::__construct($path);
    }

    public function __toString(): string
    {
        return ($this->nullCheck ? '?->' : '->').$this->getPath();
    }
}
