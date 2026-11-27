UPGRADE FROM 3.x to 4.0
=======================

> [!NOTE]
> Version 4 requires PHP 8.4.1 or higher

After the upgrade, delete the generated files in your cache directory and compile them again.

Annotations
-----------

 * `doctrine/annotations` is no longer installed. Models with JMS attributes need no change: create the parser with
   `new JMSParser()`. If your models still use Doctrine annotations, run `composer require doctrine/annotations` and
   pass the reader to the parser:

   ```php
   new JMSParser(new AnnotationReader());
   ```

Configuration
-------------

 * Remove `GeneratorConfiguration::createFomArray()`, use `SerializerConfigBuilder` instead

   *Before*
   ```php
   use Liip\Serializer\Compiler;
   use Liip\Serializer\Configuration\GeneratorConfiguration;
   use Liip\Serializer\DeserializerGenerator;
   use Liip\Serializer\Serializer;
   use Liip\Serializer\SerializerGenerator;
   use Liip\Serializer\Template\Deserialization;
   use Liip\Serializer\Template\Serialization;

   $configuration = GeneratorConfiguration::createFomArray([
       'options' => [
           'allow_generic_arrays' => true,
           'handlers' => [new UuidHandler()],
           'generation' => [
               'serialization' => ['serialize_null' => true],
               'deserialization' => ['null_as_default' => false],
           ],
       ],
       'default_group_combinations' => [['api']],
       'default_versions' => ['', '1', '2'],
       'classes' => [
           Product::class => [
               'default_versions' => ['1', '2'],
               'group_combinations' => [
                   ['groups' => []],
                   ['groups' => ['api', 'detail'], 'versions' => ['2']],
               ],
           ],
           Other::class => [],
       ],
   ]);

   $serializerGenerator = new SerializerGenerator(new Serialization(), $configuration, $cacheDirectory);
   $deserializerGenerator = new DeserializerGenerator(new Deserialization(), [Product::class, Other::class], $cacheDirectory, $configuration);
   (new Compiler($builder, $deserializerGenerator, $serializerGenerator))->compile();

   $serializer = new Serializer($cacheDirectory, $dispatcher);
   ```

   *After*
   ```php
   use Liip\Serializer\Configuration\GroupCombination;
   use Liip\Serializer\Configuration\SerializerConfigBuilder;

   $config = SerializerConfigBuilder::create($cacheDirectory)
       ->withMetadataBuilder($builder)
       ->withAllowGenericArrays()
       ->withHandlers([new UuidHandler()])
       ->withSerializeNull()
       ->withNullAsDefault(false)
       ->withEventDispatcher($dispatcher)
       ->withClass(
           Product::class,
           versions: ['1', '2'],
           groupCombinations: [
               new GroupCombination([]),
               new GroupCombination(['api', 'detail'], versions: ['2']),
           ],
       )
       ->withClass(Other::class, versions: ['', '1', '2'], groupCombinations: [new GroupCombination(['api'])]);

   $config->buildCompiler()->compile();
   $serializer = $config->buildSerializer();
   ```

 * Global defaults for versions and group combinations are removed. Pass `versions` and `groupCombinations` to each
   `withClass()`/`withClasses()` call
 * The options map to these builder methods:

   | 3.x option                                       | 4.0 builder method           |
   |--------------------------------------------------|------------------------------|
   | `allow_generic_arrays`                           | `withAllowGenericArrays()`   |
   | `handlers`                                       | `withHandlers()`             |
   | `generation.serialization.serialize_null`        | `withSerializeNull()`        |
   | `generation.deserialization.null_as_default`     | `withNullAsDefault()`        |

 * `GeneratorConfiguration` is now `final readonly` and no longer implements `\IteratorAggregate`. Remove the methods
   `addClassToGenerate()`, `getDefaultVersions()` and `getDefaultGroupCombinations()`, use `getSerializerClasses()`
   and `getDeserializerClasses()` instead
 * `ClassToGenerate` is now `final readonly` and no longer implements `\IteratorAggregate`. Its constructor no longer
   takes a `GeneratorConfiguration`. Remove the methods `getDefaultVersions()` and `addGroupCombination()`, use
   `getGroupCombinations()` and `getVersions()` instead
 * `GroupCombination` is now `final readonly`. Its constructor no longer takes the containing `ClassToGenerate`

   *Before*
   ```php
   new GroupCombination($classToGenerate, ['api'], ['2']);
   ```

   *After*
   ```php
   new GroupCombination(['api'], ['2']);
   ```

Generators
----------

 * `DeserializerGenerator::__construct()` takes a `GeneratorConfiguration` as second argument instead of a list of
   classes. The classes come from `GeneratorConfiguration::getDeserializerClasses()`

   *Before*
   ```php
   new DeserializerGenerator(new Deserialization(), [Product::class], $cacheDirectory, $configuration);
   ```

   *After*
   ```php
   new DeserializerGenerator(new Deserialization(), $configuration, $cacheDirectory);
   ```

Serializer
----------

 * Remove `Recursion::check()`. `Recursion` is now `final` and `@internal`
 * With `serialize_null`, a class whose properties are all left out (because of `SkipWhenEmpty`,
   `Exclude(if: ...)`/`Expose(if: ...)` or `MaxDepth`) is serialized as an empty object `{}` instead of an empty
   array `[]`
