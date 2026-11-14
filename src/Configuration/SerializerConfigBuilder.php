<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

use Liip\MetadataParser\Builder;
use Liip\Serializer\Compiler;
use Liip\Serializer\DeserializerGenerator;
use Liip\Serializer\DeserializerHandlerInterface;
use Liip\Serializer\Serializer;
use Liip\Serializer\SerializerGenerator;
use Liip\Serializer\SerializerHandlerInterface;
use Liip\Serializer\Template\Deserialization;
use Liip\Serializer\Template\Serialization;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class SerializerConfigBuilder
{
    private ?Builder $metadataBuilder = null;

    /**
     * @var list<array{className: class-string, versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null}>
     */
    private array $serializerClasses = [];

    /**
     * @var list<class-string>
     */
    private array $deserializerClasses = [];

    /**
     * @var list<array{namespace: string, directory: string, versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null}>
     */
    private array $serializerPaths = []; // @phpstan-ignore property.onlyWritten

    /**
     * @var list<array{namespace: string, directory: string}>
     */
    private array $deserializerPaths = []; // @phpstan-ignore property.onlyWritten

    /**
     * @var list<string>
     */
    private array $skip = [];

    /**
     * @var list<string|int>
     */
    private array $defaultVersions = [''];

    /**
     * @var list<list<string>>
     */
    private array $defaultGroupCombinations = [[]];

    private bool $allowGenericArrays = false;

    private bool $serializeNull = false;

    private bool $nullAsDefault = true;

    /**
     * @var list<SerializerHandlerInterface|DeserializerHandlerInterface>
     */
    private array $handlers = [];

    private ?EventDispatcherInterface $eventDispatcher = null;

    private ?ExpressionLanguage $expressionLanguage = null;

    public function __construct(private readonly string $cacheDirectory)
    {
    }

    public function withMetadataBuilder(Builder $builder): static
    {
        $this->metadataBuilder = $builder;

        return $this;
    }

    /**
     * @param list<class-string> $classNames
     */
    public function withClasses(array $classNames): static
    {
        foreach ($classNames as $className) {
            $this->withClass($className);
        }

        return $this;
    }

    /**
     * @param list<class-string> $classNames
     */
    public function withSerializerClasses(array $classNames): static
    {
        foreach ($classNames as $className) {
            $this->withClass($className, target: GenerationTarget::Serializer);
        }

        return $this;
    }

    /**
     * @param list<class-string> $classNames
     */
    public function withDeserializerClasses(array $classNames): static
    {
        foreach ($classNames as $className) {
            $this->withClass($className, target: GenerationTarget::Deserializer);
        }

        return $this;
    }

    /**
     * @param class-string                $className
     * @param list<string|int>|null       $versions
     * @param list<GroupCombination>|null $groupCombinations
     */
    public function withClass(string $className, ?array $versions = null, ?array $groupCombinations = null, GenerationTarget $target = GenerationTarget::Both): static
    {
        if ($target->includesSerializer()) {
            $this->serializerClasses[] = ['className' => $className, 'versions' => $versions, 'groupCombinations' => $groupCombinations];
        }
        if ($target->includesDeserializer()) {
            $this->deserializerClasses[] = $className;
        }

        return $this;
    }

    /**
     * @param list<string|int>|null       $versions
     * @param list<GroupCombination>|null $groupCombinations
     */
    public function withPath(string $namespace, string $directory, ?array $versions = null, ?array $groupCombinations = null): static
    {
        $this->withSerializerPath($namespace, $directory, $versions, $groupCombinations);

        return $this->withDeserializerPath($namespace, $directory);
    }

    /**
     * @param list<string|int>|null       $versions
     * @param list<GroupCombination>|null $groupCombinations
     */
    public function withSerializerPath(string $namespace, string $directory, ?array $versions = null, ?array $groupCombinations = null): static
    {
        $this->serializerPaths[] = ['namespace' => $namespace, 'directory' => $directory, 'versions' => $versions, 'groupCombinations' => $groupCombinations];

        return $this;
    }

    public function withDeserializerPath(string $namespace, string $directory): static
    {
        $this->deserializerPaths[] = ['namespace' => $namespace, 'directory' => $directory];

        return $this;
    }

    /**
     * @param list<string> $skip
     */
    public function withSkip(array $skip): static
    {
        $this->skip = [...$this->skip, ...$skip];

        return $this;
    }

    /**
     * @param list<string|int> $versions
     */
    public function withDefaultVersions(array $versions): static
    {
        $this->defaultVersions = $versions;

        return $this;
    }

    /**
     * @param list<list<string>> $groupCombinations
     */
    public function withDefaultGroupCombinations(array $groupCombinations): static
    {
        $this->defaultGroupCombinations = $groupCombinations;

        return $this;
    }

    public function withAllowGenericArrays(bool $allow = true): static
    {
        $this->allowGenericArrays = $allow;

        return $this;
    }

    public function withSerializeNull(bool $serializeNull = true): static
    {
        $this->serializeNull = $serializeNull;

        return $this;
    }

    public function withNullAsDefault(bool $nullAsDefault): static
    {
        $this->nullAsDefault = $nullAsDefault;

        return $this;
    }

    /**
     * @param list<SerializerHandlerInterface|DeserializerHandlerInterface> $handlers
     */
    public function withHandlers(array $handlers): static
    {
        $this->handlers = [...$this->handlers, ...$handlers];

        return $this;
    }

    public function withEventDispatcher(EventDispatcherInterface $eventDispatcher): static
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function withExpressionLanguage(ExpressionLanguage $expressionLanguage): static
    {
        $this->expressionLanguage = $expressionLanguage;

        return $this;
    }

    public function buildSerializer(): Serializer
    {
        return new Serializer($this->cacheDirectory, $this->eventDispatcher, $this->expressionLanguage);
    }

    public function buildCompiler(): Compiler
    {
        if (null === $this->metadataBuilder) {
            throw new \LogicException('Call withMetadataBuilder() before buildCompiler().');
        }

        $serializerClasses = array_map($this->resolveClass(...), $this->serializerClasses);
        if ([] === $serializerClasses && [] === $this->deserializerClasses) {
            throw new \LogicException('No classes to generate. Add classes or paths.');
        }

        $configuration = new GeneratorConfiguration(
            $serializerClasses,
            $this->deserializerClasses,
            $this->allowGenericArrays,
            null !== $this->expressionLanguage,
            $this->handlers,
            $this->serializeNull,
            $this->nullAsDefault,
        );

        return new Compiler(
            $this->metadataBuilder,
            new DeserializerGenerator(new Deserialization(), $configuration, $this->cacheDirectory),
            new SerializerGenerator(new Serialization(), $configuration, $this->cacheDirectory),
        );
    }

    /**
     * @param array{className: class-string, versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null} $entry
     */
    private function resolveClass(array $entry): ClassToGenerate
    {
        $groupCombinations = $entry['groupCombinations']
            ?? array_map(static fn (array $groups): GroupCombination => new GroupCombination($groups), $this->defaultGroupCombinations);

        return new ClassToGenerate(
            $entry['className'],
            array_map(strval(...), $entry['versions'] ?? $this->defaultVersions),
            $groupCombinations,
        );
    }
}
