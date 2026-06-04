<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Unit;

use Doctrine\Common\Annotations\AnnotationReader;
use Liip\MetadataParser\Builder;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\Serializer\Context;
use Liip\Serializer\Exception\Exception;
use Liip\Serializer\Exception\UnsupportedFormatException;
use Liip\Serializer\Exception\UnsupportedTypeException;
use Liip\Serializer\Serializer;
use PHPUnit\Framework\Attributes\Small;
use Tests\Liip\Serializer\Fixtures\MultidimensionalArrayForPrimitive;
use Tests\Liip\Serializer\Fixtures\SerializerFailureModel;
use Tests\Liip\Serializer\Fixtures\SerializerModel;

#[Small]
class SerializerTest extends SerializerTestCase
{
    private static Builder $metadataBuilder;

    public static function setUpBeforeClass(): void
    {
        static::$metadataBuilder = self::createMetadataBuilder([
            new ReflectionParser(),
            new PhpDocParser(),
            new JMSParser(new AnnotationReader()),
        ]);
    }

    public function testSerialize(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');

        $context = new Context();
        $context->setVersion('2');
        $context->setGroups(['api']);
        $json = $transform->serialize(new SerializerModel(), 'json', $context);
        self::assertSame('{"seen":true}', $json);
    }

    public function testSerializeFailOnFormat(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');
        $model = new SerializerModel();
        $context = new Context();
        $context->setVersion('2');
        $context->setGroups(['api']);

        $this->expectException(UnsupportedFormatException::class);
        $transform->serialize($model, 'xml', $context);
    }

    public function testSerializeFailOnFileNotExists(): void
    {
        $transform = new Serializer('/tmp/foo');
        $model = new SerializerModel();
        $context = new Context();
        $context->setVersion('2');
        $context->setGroups(['api']);

        $this->expectException(UnsupportedTypeException::class);
        $transform->serialize($model, 'json', $context);
    }

    public function testSerializeFailOnError(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');
        $model = new SerializerFailureModel();
        $context = new Context();
        $context->setVersion('2');
        $context->setGroups(['api']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error during serialization');
        $transform->serialize($model, 'json', $context);
    }

    public function testToArray(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');

        $context = new Context();
        $context->setVersion('2');
        $context->setGroups(['api']);
        $json = $transform->toArray(new SerializerModel(), $context);
        self::assertSame(['seen' => true], $json);
    }

    public function testToArrayNoContext(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');

        $json = $transform->toArray(new SerializerModel());
        self::assertSame(['all' => true], $json);
    }

    public function testDeserialize(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');

        $data = $transform->deserialize('[]', SerializerModel::class, 'json');
        self::assertInstanceOf(SerializerModel::class, $data);
        self::assertSame('deserializer', $data->field);
    }

    public function testDeserializeFailOnFormat(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');

        $this->expectException(UnsupportedFormatException::class);
        $transform->deserialize('<xml-data/>', SerializerModel::class, 'xml');
    }

    public function testDeserializeFailOnFileNotExists(): void
    {
        $transform = new Serializer('/tmp/foo');

        $this->expectException(UnsupportedTypeException::class);
        $transform->deserialize('{"it":"works"}', SerializerModel::class, 'json');
    }

    public function testDeserializeFailOnError(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error during deserialization');
        $transform->deserialize('{"it":"fails horribly"}', SerializerFailureModel::class, 'json');
    }

    public function testFromArrayFailOnVersion(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');
        $context = new Context();
        $context->setVersion('2');
        $context->setGroups(['api']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Version and group support is not implemented for deserialization');
        $transform->fromArray(['it' => 'works'], SerializerModel::class, $context);
    }

    public function testFromArrayNoContext(): void
    {
        $transform = new Serializer(__DIR__.'/../Fixtures');

        $data = $transform->fromArray([], SerializerModel::class);
        self::assertInstanceOf(SerializerModel::class, $data);
        self::assertSame('deserializer', $data->field);
    }

    public function testHashmapSerialization(): void
    {
        $functionName = 'serialize_Tests_Liip_Serializer_Fixtures_MultidimensionalArrayForPrimitive';
        self::generateSerializers(self::$metadataBuilder, MultidimensionalArrayForPrimitive::class, [$functionName], ['']);

        $serializer = new Serializer('/tmp');

        $subject = new MultidimensionalArrayForPrimitive();
        $twoDims = [0 => [0], 1 => [1]];
        $fiveDims = [0 => [0 => [0 => [0 => [2]]]], 1 => [3 => [0 => [0 => [3]]]]];
        $mapOfLists = ['foo' => [0], 'bar' => [1]];
        $listOfMapOfLists = [0 => ['m00' => [0 => 0, 1 => 1], 'm01' => [0 => 0]], 1 => ['m10' => [0 => 42]]];

        $subject->twoDims = $twoDims;
        $subject->fiveDims = $fiveDims;
        $subject->mapOfLists = $mapOfLists;
        $subject->listOfMapOfLists = $listOfMapOfLists;

        $json = $serializer->serialize($subject, 'json');

        self::assertSame('{"two_dims":{"0":[0],"1":[1]},"five_dims":{"0":{"0":{"0":{"0":[2]}}},"1":{"3":{"0":{"0":[3]}}}},"map_of_lists":{"foo":[0],"bar":[1]},"list_of_map_of_lists":{"0":{"m00":[0,1],"m01":[0]},"1":{"m10":[42]}}}', $json);
    }
}
