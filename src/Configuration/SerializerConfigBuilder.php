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
     * @var array<class-string, array{versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null, target: GenerationTarget}>
     */
    private array $classEntries = [];

    /**
     * @var list<class-string>
     */
    private array $plainSerializerClasses = [];

    /**
     * @var list<class-string>
     */
    private array $plainDeserializerClasses = [];

    /**
     * @var list<array{namespace: string, directory: string, versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null}>
     */
    private array $serializerPaths = [];

    /**
     * @var list<array{namespace: string, directory: string}>
     */
    private array $deserializerPaths = [];

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
        $classNames = self::normalizeClassNames($classNames);
        $this->plainSerializerClasses = [...$this->plainSerializerClasses, ...$classNames];
        $this->plainDeserializerClasses = [...$this->plainDeserializerClasses, ...$classNames];

        return $this;
    }

    /**
     * @param list<class-string> $classNames
     */
    public function withSerializerClasses(array $classNames): static
    {
        $this->plainSerializerClasses = [...$this->plainSerializerClasses, ...self::normalizeClassNames($classNames)];

        return $this;
    }

    /**
     * @param list<class-string> $classNames
     */
    public function withDeserializerClasses(array $classNames): static
    {
        $this->plainDeserializerClasses = [...$this->plainDeserializerClasses, ...self::normalizeClassNames($classNames)];

        return $this;
    }

    /**
     * @param class-string                $className
     * @param list<string|int>|null       $versions
     * @param list<GroupCombination>|null $groupCombinations
     */
    public function withClass(string $className, ?array $versions = null, ?array $groupCombinations = null, GenerationTarget $target = GenerationTarget::Both): static
    {
        self::assertVersions($versions);
        self::assertGroupCombinations($groupCombinations);
        $this->classEntries[ltrim($className, '\\')] = ['versions' => $versions, 'groupCombinations' => $groupCombinations, 'target' => $target];

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
        self::assertVersions($versions);
        self::assertGroupCombinations($groupCombinations);
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
        $this->skip = [...$this->skip, ...array_map(static fn (string $entry): string => str_contains($entry, '/') ? $entry : ltrim($entry, '\\'), $skip)];

        return $this;
    }

    /**
     * @param list<string|int> $versions
     */
    public function withDefaultVersions(array $versions): static
    {
        self::assertVersions($versions);
        $this->defaultVersions = $versions;

        return $this;
    }

    /**
     * @param list<list<string>> $groupCombinations
     */
    public function withDefaultGroupCombinations(array $groupCombinations): static
    {
        if ([] === $groupCombinations) {
            throw new \InvalidArgumentException('Group combination list may not be empty. To generate without groups, specify a list with an empty list: [[]].');
        }

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

        $discovery = new ClassDiscovery($this->skip);
        $serializerClasses = $this->resolveSerializerClasses($discovery);
        $deserializerClasses = $this->resolveDeserializerClasses($discovery);
        if ([] === $serializerClasses && [] === $deserializerClasses) {
            throw new \LogicException('No classes to generate. Add classes or paths.');
        }

        $configuration = new GeneratorConfiguration(
            $serializerClasses,
            $deserializerClasses,
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
     * @return list<ClassToGenerate>
     */
    private function resolveSerializerClasses(ClassDiscovery $discovery): array
    {
        /** @var array<class-string, array{versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null}> $merged */
        $merged = [];
        foreach ($this->serializerPaths as $path) {
            foreach ($discovery->discover($path['namespace'], $path['directory']) as $className) {
                $merged[$className] = $this->mergePathConfig($className, $merged[$className] ?? null, $path);
            }
        }

        foreach ($this->plainSerializerClasses as $className) {
            $merged[$className] ??= ['versions' => null, 'groupCombinations' => null];
        }

        foreach ($this->classEntries as $className => $entry) {
            if (!$entry['target']->includesSerializer()) {
                continue;
            }

            $current = $merged[$className] ?? ['versions' => null, 'groupCombinations' => null];
            $merged[$className] = [
                'versions' => $entry['versions'] ?? $current['versions'],
                'groupCombinations' => $entry['groupCombinations'] ?? $current['groupCombinations'],
            ];
        }

        $defaultGroupCombinations = array_map(static fn (array $groups): GroupCombination => new GroupCombination($groups), $this->defaultGroupCombinations);
        $classes = [];
        foreach ($merged as $className => $config) {
            $classes[] = new ClassToGenerate(
                $className,
                array_map(strval(...), $config['versions'] ?? $this->defaultVersions),
                $config['groupCombinations'] ?? $defaultGroupCombinations,
            );
        }

        return $classes;
    }

    /**
     * @return list<class-string>
     */
    private function resolveDeserializerClasses(ClassDiscovery $discovery): array
    {
        $classes = [];
        foreach ($this->deserializerPaths as $path) {
            foreach ($discovery->discover($path['namespace'], $path['directory']) as $className) {
                $classes[$className] = $className;
            }
        }

        foreach ($this->plainDeserializerClasses as $className) {
            $classes[$className] = $className;
        }

        foreach ($this->classEntries as $className => $entry) {
            if ($entry['target']->includesDeserializer()) {
                $classes[$className] = $className;
            }
        }

        return array_values($classes);
    }

    /**
     * @param array{versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null}|null                                  $current
     * @param array{namespace: string, directory: string, versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null} $path
     *
     * @return array{versions: list<string|int>|null, groupCombinations: list<GroupCombination>|null}
     */
    private function mergePathConfig(string $className, ?array $current, array $path): array
    {
        if (null === $current) {
            return ['versions' => $path['versions'], 'groupCombinations' => $path['groupCombinations']];
        }

        $versions = $this->mergeField($className, $current['versions'], $path['versions'], self::versionsKey(...));
        $groupCombinations = $this->mergeField($className, $current['groupCombinations'], $path['groupCombinations'], self::groupCombinationsKey(...));

        return ['versions' => $versions, 'groupCombinations' => $groupCombinations];
    }

    /**
     * @template T of array
     *
     * @param T|null                    $current
     * @param T|null                    $new
     * @param callable(T): list<string> $key
     *
     * @return T|null
     */
    private function mergeField(string $className, ?array $current, ?array $new, callable $key): ?array
    {
        if (null === $current) {
            return $new;
        }
        if (null === $new || $key($current) === $key($new)) {
            return $current;
        }

        throw new \LogicException(\sprintf('Class "%s" is found by more than one path with different versions or group combinations. Configure it with withClass().', $className));
    }

    /**
     * @param list<string|int> $versions
     *
     * @return list<string>
     */
    private static function versionsKey(array $versions): array
    {
        $keys = array_map(strval(...), $versions);
        sort($keys, \SORT_STRING);

        return $keys;
    }

    /**
     * @param list<GroupCombination> $groupCombinations
     *
     * @return list<string>
     */
    private static function groupCombinationsKey(array $groupCombinations): array
    {
        $keys = array_map(
            static fn (GroupCombination $combination): string => json_encode([$combination->getGroups(), null === $combination->getVersions() ? null : self::versionsKey($combination->getVersions())], \JSON_THROW_ON_ERROR),
            $groupCombinations,
        );
        sort($keys, \SORT_STRING);

        return $keys;
    }

    /**
     * @param list<string> $classNames
     *
     * @return list<class-string>
     */
    private static function normalizeClassNames(array $classNames): array
    {
        /** @var list<class-string> $normalized */
        $normalized = array_map(static fn (string $className): string => ltrim($className, '\\'), $classNames);

        return $normalized;
    }

    /**
     * @param list<string|int>|null $versions
     */
    private static function assertVersions(?array $versions): void
    {
        if ([] === $versions) {
            throw new \InvalidArgumentException('Version list may not be empty. To generate without version, specify an empty string. To use the default versions, pass null.');
        }
    }

    /**
     * @param list<mixed>|null $groupCombinations
     */
    private static function assertGroupCombinations(?array $groupCombinations): void
    {
        if (null === $groupCombinations) {
            return;
        }
        if ([] === $groupCombinations) {
            throw new \InvalidArgumentException('Group combination list may not be empty. To generate without groups, specify a list with new GroupCombination([]). To use the default group combinations, pass null.');
        }
        foreach ($groupCombinations as $groupCombination) {
            if (!$groupCombination instanceof GroupCombination) {
                throw new \InvalidArgumentException(\sprintf('Group combinations must be instances of "%s".', GroupCombination::class));
            }
        }
    }
}
