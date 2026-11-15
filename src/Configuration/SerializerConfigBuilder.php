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
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SerializerConfigBuilder
{
    private ?Builder $metadataBuilder = null;

    /**
     * @var array<class-string, ClassToGenerate>
     */
    private array $serializerClasses = [];

    /**
     * @var array<class-string, ClassToGenerate>
     */
    private array $deserializerClasses = [];

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

    public static function create(string $cacheDirectory): self
    {
        return new self($cacheDirectory);
    }

    public function withMetadataBuilder(Builder $builder): static
    {
        $this->metadataBuilder = $builder;

        return $this;
    }

    /**
     * @param list<class-string>     $classNames
     * @param list<string|int>       $versions
     * @param list<GroupCombination> $groupCombinations
     */
    public function withClasses(array $classNames, array $versions = [''], array $groupCombinations = [new GroupCombination([])]): static
    {
        foreach ($classNames as $className) {
            $this->withClass($className, $versions, $groupCombinations);
        }

        return $this;
    }

    /**
     * @param list<class-string>     $classNames
     * @param list<string|int>       $versions
     * @param list<GroupCombination> $groupCombinations
     */
    public function withClassesForSerializer(array $classNames, array $versions = [''], array $groupCombinations = [new GroupCombination([])]): static
    {
        foreach ($classNames as $className) {
            $this->withClassForSerializer($className, $versions, $groupCombinations);
        }

        return $this;
    }

    /**
     * @param list<class-string> $classNames
     */
    public function withClassesForDeserializer(array $classNames): static
    {
        foreach ($classNames as $className) {
            $this->withClassForDeserializer($className);
        }

        return $this;
    }

    /**
     * @param class-string           $className
     * @param list<string|int>       $versions
     * @param list<GroupCombination> $groupCombinations
     */
    public function withClass(string $className, array $versions = [''], array $groupCombinations = [new GroupCombination([])]): static
    {
        $classToGenerate = $this->createClassToGenerate($className, $versions, $groupCombinations);
        $this->serializerClasses[$classToGenerate->getClassName()] = $classToGenerate;
        $this->deserializerClasses[$classToGenerate->getClassName()] = $classToGenerate;

        return $this;
    }

    /**
     * @param class-string           $className
     * @param list<string|int>       $versions
     * @param list<GroupCombination> $groupCombinations
     */
    public function withClassForSerializer(string $className, array $versions = [''], array $groupCombinations = [new GroupCombination([])]): static
    {
        $classToGenerate = $this->createClassToGenerate($className, $versions, $groupCombinations);
        $this->serializerClasses[$classToGenerate->getClassName()] = $classToGenerate;

        return $this;
    }

    /**
     * @param class-string $className
     */
    public function withClassForDeserializer(string $className): static
    {
        $classToGenerate = $this->createClassToGenerate($className, [''], [new GroupCombination([])]);
        $this->deserializerClasses[$classToGenerate->getClassName()] = $classToGenerate;

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

        $configuration = new GeneratorConfiguration(
            array_values($this->serializerClasses),
            array_values($this->deserializerClasses),
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
     * @param class-string           $className
     * @param list<string|int>       $versions
     * @param list<GroupCombination> $groupCombinations
     */
    private function createClassToGenerate(string $className, array $versions, array $groupCombinations): ClassToGenerate
    {
        $this->assertVersions($versions);
        $this->assertGroupCombinations($groupCombinations);

        /** @var class-string $normalizedClassName */
        $normalizedClassName = ltrim($className, '\\');

        return new ClassToGenerate($normalizedClassName, array_map(strval(...), $versions), $groupCombinations);
    }

    /**
     * @param list<string|int> $versions
     */
    private function assertVersions(array $versions): void
    {
        if ([] === $versions) {
            throw new \InvalidArgumentException('Version list may not be empty. To generate without version, specify an empty string.');
        }
    }

    /**
     * @param list<mixed> $groupCombinations
     */
    private function assertGroupCombinations(array $groupCombinations): void
    {
        if ([] === $groupCombinations) {
            throw new \InvalidArgumentException('Group combination list may not be empty. To generate without groups, specify a list with new GroupCombination([]).');
        }
        foreach ($groupCombinations as $groupCombination) {
            if (!$groupCombination instanceof GroupCombination) {
                throw new \InvalidArgumentException(\sprintf('Group combinations must be instances of "%s".', GroupCombination::class));
            }
        }
    }
}
