<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Performance;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Collections\ArrayCollection;
use Liip\MetadataParser\Builder;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\MetadataParser\Parser;
use Liip\MetadataParser\RecursionChecker;
use Liip\Serializer\Configuration\GeneratorConfiguration;
use Liip\Serializer\DeserializerGenerator;
use Liip\Serializer\Serializer;
use Liip\Serializer\SerializerGenerator;
use Liip\Serializer\Template\Deserialization;
use Liip\Serializer\Template\Serialization;
use Tests\Liip\Serializer\Fixtures\ListModel;
use Tests\Liip\Serializer\Fixtures\Nested;

final class BenchmarkFactory
{
    private const int LIST_SIZE = 2000;
    private const int HASHMAP_SIZE = 200;
    private const int COLLECTION_SIZE = 100;
    private const int INNER_ARRAY_SIZE = 10;

    public static function cacheDir(): string
    {
        return sys_get_temp_dir().'/rebuy-serializer-bench';
    }

    public static function generateFiles(): void
    {
        $cacheDir = self::cacheDir();

        $builder = new Builder(
            new Parser([
                new ReflectionParser(),
                new PhpDocParser(),
                new JMSParser(new AnnotationReader()),
            ]),
            new RecursionChecker(null, []),
        );

        $configuration = GeneratorConfiguration::createFomArray([
            'default_group_combinations' => [[]],
            'default_versions' => [''],
            'classes' => [
                ListModel::class => [],
            ],
        ]);

        new SerializerGenerator(new Serialization(), $configuration, $cacheDir)->generate($builder);
        new DeserializerGenerator(new Deserialization(), [ListModel::class], $cacheDir)->generate($builder);
    }

    public static function createSerializer(): Serializer
    {
        return new Serializer(self::cacheDir());
    }

    public static function createLargeModel(): ListModel
    {
        $model = new ListModel();

        $model->array = self::createStringArray(self::INNER_ARRAY_SIZE);

        $model->listNested = [];
        for ($i = 0; $i < self::LIST_SIZE; ++$i) {
            $model->listNested[] = self::createNestedArray($i);
        }

        $model->hashmap = [];
        for ($i = 0; $i < self::HASHMAP_SIZE; ++$i) {
            $model->hashmap['key'.$i] = self::createNestedArray($i);
        }

        $model->collection = new ArrayCollection(self::createStringArray(self::COLLECTION_SIZE));

        $collectionNested = [];
        for ($i = 0; $i < self::COLLECTION_SIZE; ++$i) {
            $collectionNested['ckey'.$i] = self::createNestedArray($i);
        }
        $model->collectionNested = new ArrayCollection($collectionNested);

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public static function createLargeArray(): array
    {
        return self::createSerializer()->toArray(self::createLargeModel());
    }

    public static function createSmallModel(): ListModel
    {
        $model = new ListModel();
        $model->array = self::createStringArray(3);
        $model->listNested = [self::createNestedArray(0), self::createNestedArray(1)];

        return $model;
    }

    private static function createNestedArray(int $i): Nested
    {
        $nested = new Nested('nested'.$i);
        $nested->array = self::createStringArray(self::INNER_ARRAY_SIZE);

        return $nested;
    }

    /**
     * @return list<string>
     */
    private static function createStringArray(int $count): array
    {
        $strings = [];
        for ($i = 0; $i < $count; ++$i) {
            $strings[] = 'value'.$i;
        }

        return $strings;
    }
}
