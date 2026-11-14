<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Unit;

use Liip\MetadataParser\Builder;
use Liip\MetadataParser\ModelParser\ModelParserInterface;
use Liip\MetadataParser\Parser;
use Liip\MetadataParser\RecursionChecker;
use Liip\Serializer\Configuration\ClassToGenerate;
use Liip\Serializer\Configuration\GeneratorConfiguration;
use Liip\Serializer\Configuration\GroupCombination;
use Liip\Serializer\DeserializerGenerator;
use Liip\Serializer\DeserializerHandlerInterface;
use Liip\Serializer\SerializerGenerator;
use Liip\Serializer\SerializerHandlerInterface;
use Liip\Serializer\Template\Deserialization;
use Liip\Serializer\Template\Serialization;
use PHPUnit\Framework\TestCase;

class SerializerTestCase extends TestCase
{
    /**
     * @param ModelParserInterface[] $parsers
     * @param string[][]             $expectedRecursions List of expected recursions
     */
    protected static function createMetadataBuilder(array $parsers, array $expectedRecursions = []): Builder
    {
        return new Builder(new Parser($parsers), new RecursionChecker(null, $expectedRecursions));
    }

    /**
     * Generate the deserializer for the specified class and make sure the file and function exist.
     *
     * @param array{allowGenericArrays?: bool, expressionLanguage?: bool, handlers?: list<SerializerHandlerInterface|DeserializerHandlerInterface>, serializeNull?: bool, nullAsDefault?: bool} $options
     */
    protected static function generateDeserializer(Builder $metadataBuilder, string $classToGenerate, string $functionName, array $options = []): void
    {
        $templating = new Deserialization();
        $configuration = new GeneratorConfiguration(...$options, deserializerClasses: [$classToGenerate]);
        $deserializerGenerator = new DeserializerGenerator($templating, $configuration, '/tmp');

        $deserializerGenerator->generate($metadataBuilder);

        $filePath = '/tmp/'.$functionName.'.php';
        self::assertFileExists($filePath);
        require_once $filePath;
        self::assertTrue(\function_exists($functionName));
    }

    /**
     * @param list<string>                                                                                                                                                                      $functionNames
     * @param list<string>                                                                                                                                                                      $versions
     * @param list<list<string>>                                                                                                                                                                $groups
     * @param array{allowGenericArrays?: bool, expressionLanguage?: bool, handlers?: list<SerializerHandlerInterface|DeserializerHandlerInterface>, serializeNull?: bool, nullAsDefault?: bool} $options
     */
    protected static function generateSerializers(Builder $metadataBuilder, string $classToGenerate, array $functionNames, array $versions = ['2'], array $groups = [], array $options = []): void
    {
        $templating = new Serialization();
        $groupCombinations = [] === $groups
            ? [new GroupCombination([])]
            : array_map(static fn (array $combination): GroupCombination => new GroupCombination($combination), $groups);
        $configuration = new GeneratorConfiguration(
            ...$options,
            serializerClasses: [new ClassToGenerate($classToGenerate, $versions, $groupCombinations)],
        );
        $serializerGenerator = new SerializerGenerator($templating, $configuration, '/tmp');

        $serializerGenerator->generate($metadataBuilder);

        foreach ($functionNames as $functionName) {
            $filePath = '/tmp/'.$functionName.'.php';
            self::assertFileExists($filePath);
            require_once $filePath;
            self::assertTrue(\function_exists($functionName));
        }
    }
}
