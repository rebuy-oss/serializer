<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

use Liip\Serializer\DeserializerHandlerInterface;
use Liip\Serializer\SerializerHandlerInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configuration for the serializer generator.
 *
 * The configuration has a list of default group combinations, a list of
 * default versions, and a list of classes. For each class, you can overwrite
 * the group combinations. For each group combination, you can again overwrite
 * the versions to generate.
 *
 * @implements \IteratorAggregate<int, ClassToGenerate>
 */
class GeneratorConfiguration implements \IteratorAggregate
{
    /**
     * A list of group combinations.
     *
     * @see GroupCombination::$groups
     *
     * @var list<list<string>>
     */
    private array $defaultGroupCombinations;

    /**
     * List of versions to generate. An empty string '' means to generate without a version.
     * e.g. ['', '2', '3']
     *
     * @var list<string>
     */
    private array $defaultVersions;

    /**
     * @var ClassToGenerate[]
     */
    private array $classesToGenerate = [];

    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @param list<list<string>>   $defaultGroupCombinations
     * @param list<string|int>     $defaultVersions
     * @param array<string, mixed> $options
     */
    public function __construct(array $defaultGroupCombinations, array $defaultVersions, array $options = [])
    {
        $this->defaultGroupCombinations = $defaultGroupCombinations ?: [[]];
        $this->defaultVersions = array_map('strval', $defaultVersions) ?: [''];
        $this->options = $this->resolveOptions($options);
    }

    /**
     * @param array{
     *     'default_group_combinations'?: list<list<string>>|null,
     *     'default_versions'?: list<string>|null,
     *     'classes'?: ?array<class-string, array<string, mixed>>,
     *     'options'?: array<string, mixed|array<mixed>>,
     * } $config
     *
     * Create configuration from array definition
     *
     * [
     *     'options' => [
     *         'allow_generic_arrays' => true,
     *         'generation' => [
     *              'serialization' => ['serialize_null => true],
     *              'deserialization' => ['null_as_default => false],
     *          ]
     *     ],
     *     'default_group_combinations' => [['api']],
     *     'default_versions' => ['', '1', '2'],
     *     'classes' => [
     *          Product::class => [
     *              'default_versions' => ['1', '2'], // optional, falls back to global list
     *              'group_combinations' => [ // optional, falls back to global default_group_combinations
     *                  [
     *                      'groups' => [], // generate without groups
     *                  ],
     *                  [
     *                      'groups' => ['api'], // global groups are overwritten, not merged. versions are taken from class default
     *                  ],
     *                  [
     *                      'groups' => ['api', 'detail'],
     *                      'versions' => ['2'], // only generate the combination of api and detail for version 2
     *                  ],
     *              ],
     *          ],
     *          Other::class => [], // generate this class with default groups and versions
     *     ]
     * ]
     */
    public static function createFomArray(array $config): self
    {
        if (!\array_key_exists('classes', $config) || (is_countable($config['classes']) ? \count($config['classes']) : 0) < 1) {
            throw new \InvalidArgumentException('You need to specify the classes to generate');
        }

        $instance = new self(
            $config['default_group_combinations'] ?? [],
            $config['default_versions'] ?? [],
            $config['options'] ?? []
        );

        foreach ($config['classes'] as $className => $classConfig) {
            $classToGenerate = new ClassToGenerate($instance, $className, $classConfig['default_versions'] ?? null);
            foreach ($classConfig['group_combinations'] ?? [] as $groupCombination) {
                $classToGenerate->addGroupCombination(
                    new GroupCombination($classToGenerate, $groupCombination['groups'], $groupCombination['versions'] ?? null)
                );
            }
            $instance->addClassToGenerate($classToGenerate);
        }

        return $instance;
    }

    public function addClassToGenerate(ClassToGenerate $classToGenerate): void
    {
        $this->classesToGenerate[] = $classToGenerate;
    }

    /**
     * @return list<string>
     */
    public function getDefaultVersions(): array
    {
        return $this->defaultVersions;
    }

    /**
     * @return list<GroupCombination>
     */
    public function getDefaultGroupCombinations(ClassToGenerate $classToGenerate): array
    {
        return array_map(
            static fn (array $combination): GroupCombination => new GroupCombination($classToGenerate, $combination),
            $this->defaultGroupCombinations
        );
    }

    /**
     * If this is false, arrays with sub type PropertyTypeUnknown are treated as error.
     * If this is true, deserialize assigns the raw array and serialize just takes the raw content of the field.
     */
    public function shouldAllowGenericArrays(): bool
    {
        return $this->options['allow_generic_arrays'];
    }

    /**
     * @param class-string $className
     */
    public function findSerializerHandlerForClass(string $className): ?SerializerHandlerInterface
    {
        foreach ($this->options['handlers'] as $handler) {
            if ($handler instanceof SerializerHandlerInterface && $this->supportsClass($handler->getSerializationClasses(), $className)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param class-string $className
     */
    public function findDeserializerHandlerForClass(string $className): ?DeserializerHandlerInterface
    {
        foreach ($this->options['handlers'] as $handler) {
            if ($handler instanceof DeserializerHandlerInterface && $this->supportsClass($handler->getDeserializationClasses(), $className)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * If this is false, do not add wrap conditionals around assigning fields whose value is null
     */
    public function shouldSerializeNull(): bool
    {
        return $this->options['generation']['serialization']['serialize_null'];
    }

    /**
     * If this is false, do not add wrap conditionals around assigning fields whose value is null
     */
    public function shouldTreatNullAsDefault(): bool
    {
        return $this->options['generation']['deserialization']['null_as_default'];
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->classesToGenerate);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'allow_generic_arrays' => false,
            'handlers' => [],
            'generation' => [
                'serialization' => [
                    'serialize_null' => false,
                ],
                'deserialization' => [
                    'null_as_default' => true,
                ],
            ],
        ]);

        $resolver->setAllowedTypes('allow_generic_arrays', 'boolean');
        $resolver->setAllowedTypes('handlers', 'array');

        // This is insanity, but the issue is that symfony/options-resolver 4.x uses setDefault() for nested options, but
        //   7.x introduces setOptions() (and deprecates setDefault()), and 8.x removes setDefault entirely. PhpStan's
        //   static analysis doesn't seem to want to ignore the `if` block on low versions, but telling phpstan to ignore
        //   those errors fails on higher versions where there are no errors to ignore. Currently, the only way to get
        //   PhpStan to back off is to hide what call I'm making entirely using strings for dynamic method calls. Even
        //   the @var is necessary, otherwise, PhpStan picks up that $setOptions is a constant (not just a variable string)
        //   and is able to repeat the warnings.
        /** @var string $setOptions */
        $setOptions = 'setOptions';
        if (method_exists($resolver, $setOptions)) {
            $resolver->{$setOptions}('generation', static function (OptionsResolver $resolver, Options $_) use ($setOptions): void {
                $resolver->{$setOptions}('serialization', static function (OptionsResolver $resolver, Options $_): void {
                    $resolver->setDefault('serialize_null', false);
                    $resolver->setAllowedTypes('serialize_null', 'bool');
                });
                $resolver->{$setOptions}('deserialization', static function (OptionsResolver $resolver, Options $_): void {
                    $resolver->setDefault('null_as_default', true);
                    $resolver->setAllowedTypes('null_as_default', 'bool');
                });
            });
        } else {
            $resolver->setDefault('generation', static function (OptionsResolver $resolver): OptionsResolver {
                $resolver->setDefault('serialization', static function (OptionsResolver $resolver): OptionsResolver {
                    $resolver->setDefault('serialize_null', false);
                    $resolver->setAllowedTypes('serialize_null', 'bool');

                    return $resolver;
                });
                return $resolver->setDefault('deserialization', static function (OptionsResolver $resolver): OptionsResolver {
                    $resolver->setDefault('null_as_default', true);
                    $resolver->setAllowedTypes('null_as_default', 'bool');

                    return $resolver;
                });
            });
        }

        return $resolver->resolve($options);
    }

    /**
     * @param class-string[] $supportedClasses
     * @param class-string   $actualClass
     */
    private function supportsClass(array $supportedClasses, string $actualClass): bool
    {
        foreach ($supportedClasses as $supportedClass) {
            if (is_a($actualClass, $supportedClass, true)) {
                return true;
            }
        }

        return false;
    }
}
