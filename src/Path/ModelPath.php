<?php

declare(strict_types=1);

namespace Liip\Serializer\Path;

/**
 * Representation of a model path in PHP, e.g. $model->property1[$index]->property2, used for code generation.
 */
final class ModelPath implements \Stringable
{
    use SplitBackTrait;

    /**
     * @var AbstractEntry[]
     */
    private array $path = [];

    public function __construct(string $root)
    {
        $this->path = [new Root($root)];
    }

    public function __toString(): string
    {
        return implode('', $this->path);
    }

    /**
     * @param string[] $components
     */
    public static function tempVariable(array $components): self
    {
        return new self(lcfirst(self::distillName(...$components)));
    }

    public static function indexVariable(string $path): self
    {
        return self::inventVariable($path, 'index');
    }

    public static function inventVariable(string $path, string $prefix): self
    {
        return new self($prefix.mb_strlen($path).self::distillName($path));
    }

    public static function distillName(string ...$components): string
    {
        $name = '';

        foreach ($components as $component) {
            $parts = preg_split('/\W+/', $component);
            $parts = (false === $parts) ? [$component] : $parts;

            foreach ($parts as $part) {
                $name .= ucfirst($part);
            }
        }

        return $name;
    }

    public function withPath(string $component, bool $nullCheck = false): self
    {
        $clone = clone $this;
        $clone->path[] = new ModelEntry($component, $nullCheck);

        return $clone;
    }

    public function withArray(string $component): self
    {
        $clone = clone $this;
        $clone->path[] = new ArrayEntry($component);

        return $clone;
    }

    public function getLastEntry(): AbstractEntry
    {
        return array_last($this->path);
    }

    public function withArrayLiteral(string $component): self
    {
        $clone = clone $this;
        $clone->path[] = new ArrayEntry(var_export($component, true));

        return $clone;
    }
}
