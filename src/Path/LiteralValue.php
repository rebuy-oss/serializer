<?php

declare(strict_types=1);

namespace Liip\Serializer\Path;

class LiteralValue extends AbstractEntry
{
    private string $expression;

    public function __construct(mixed $path)
    {
        $this->expression = var_export($path, true);
        parent::__construct($this->expression);
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
