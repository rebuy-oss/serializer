<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Unit\Configuration;

use Doctrine\Common\Annotations\AnnotationReader;
use Liip\MetadataParser\Builder;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\MetadataParser\Parser;
use Liip\MetadataParser\RecursionChecker;
use Liip\Serializer\Configuration\SerializerConfig;
use Liip\Serializer\Serializer;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Tests\Liip\Serializer\Fixtures\Discovery\Concrete;
use Tests\Liip\Serializer\Fixtures\Discovery\Sub\Nested;
use Tests\Liip\Serializer\Fixtures\ExcludeIfModel;

#[Medium]
class SerializerConfigBuilderTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir().'/serializer-config-'.uniqid();
    }

    public function testBuildSerializerDoesNotNeedMetadataBuilder(): void
    {
        $serializer = SerializerConfig::configure($this->cacheDirectory)
            ->withPath('Does\Not\Exist', '/does/not/exist')
            ->buildSerializer()
        ;

        self::assertInstanceOf(Serializer::class, $serializer);
    }

    public function testBuildCompilerWithoutMetadataBuilderThrows(): void
    {
        $this->expectException(\LogicException::class);

        SerializerConfig::configure($this->cacheDirectory)
            ->withClasses([Concrete::class])
            ->buildCompiler()
        ;
    }

    public function testBuildCompilerWithoutClassesThrows(): void
    {
        $this->expectException(\LogicException::class);

        SerializerConfig::configure($this->cacheDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->buildCompiler()
        ;
    }

    public function testWithClassesGeneratesBothSides(): void
    {
        SerializerConfig::configure($this->cacheDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->withClasses([Concrete::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Discovery_Concrete.php');
        self::assertFileExists($this->cacheDirectory.'/deserialize_Tests_Liip_Serializer_Fixtures_Discovery_Concrete.php');
    }

    public function testSerializerOnlyAndDeserializerOnlyClasses(): void
    {
        SerializerConfig::configure($this->cacheDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->withSerializerClasses([Concrete::class])
            ->withDeserializerClasses([Nested::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Discovery_Concrete.php');
        self::assertFileExists($this->cacheDirectory.'/deserialize_Tests_Liip_Serializer_Fixtures_Discovery_Sub_Nested.php');
        self::assertFileDoesNotExist($this->cacheDirectory.'/deserialize_Tests_Liip_Serializer_Fixtures_Discovery_Concrete.php');
        self::assertFileDoesNotExist($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Discovery_Sub_Nested.php');
    }

    public function testDefaultsAreUsed(): void
    {
        SerializerConfig::configure($this->cacheDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->withClasses([Concrete::class])
            ->withDefaultVersions(['1', '2'])
            ->withDefaultGroupCombinations([['api']])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Discovery_Concrete_api_1.php');
        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Discovery_Concrete_api_2.php');
    }

    public function testExpressionLanguageEnablesGeneration(): void
    {
        $withDirectory = $this->cacheDirectory.'/with';
        SerializerConfig::configure($withDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->withSerializerClasses([ExcludeIfModel::class])
            ->withExpressionLanguage(new ExpressionLanguage())
            ->buildCompiler()
            ->compile()
        ;

        $withoutDirectory = $this->cacheDirectory.'/without';
        SerializerConfig::configure($withoutDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->withSerializerClasses([ExcludeIfModel::class])
            ->buildCompiler()
            ->compile()
        ;

        $file = '/serialize_Tests_Liip_Serializer_Fixtures_ExcludeIfModel.php';
        self::assertStringContainsString('expressionLanguage', (string) file_get_contents($withDirectory.$file));
        self::assertStringNotContainsString('expressionLanguage', (string) file_get_contents($withoutDirectory.$file));
    }

    private static function createMetadataBuilder(): Builder
    {
        return new Builder(
            new Parser([new ReflectionParser(), new PhpDocParser(strict: false), new JMSParser(new AnnotationReader())]),
            new RecursionChecker(null, []),
        );
    }
}
