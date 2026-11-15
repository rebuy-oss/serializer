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
use Liip\Serializer\Configuration\GenerationTarget;
use Liip\Serializer\Configuration\GroupCombination;
use Liip\Serializer\Configuration\SerializerConfig;
use Liip\Serializer\Configuration\SerializerConfigBuilder;
use Liip\Serializer\Serializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Filesystem\Filesystem;
use Tests\Liip\Serializer\Fixtures\Builder\Concrete;
use Tests\Liip\Serializer\Fixtures\Builder\Sub\Nested;
use Tests\Liip\Serializer\Fixtures\ExcludeIfModel;

#[Medium]
class SerializerConfigBuilderTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir().'/serializer-config-'.uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->cacheDirectory);
    }

    public function testBuildSerializerDoesNotNeedMetadataBuilder(): void
    {
        $serializer = SerializerConfig::configure($this->cacheDirectory)
            ->withClasses(['Does\Not\Exist'])
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

        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Builder_Concrete.php');
        self::assertFileExists($this->cacheDirectory.'/deserialize_Tests_Liip_Serializer_Fixtures_Builder_Concrete.php');
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

        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Builder_Concrete.php');
        self::assertFileExists($this->cacheDirectory.'/deserialize_Tests_Liip_Serializer_Fixtures_Builder_Sub_Nested.php');
        self::assertFileDoesNotExist($this->cacheDirectory.'/deserialize_Tests_Liip_Serializer_Fixtures_Builder_Concrete.php');
        self::assertFileDoesNotExist($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Builder_Sub_Nested.php');
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

        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Builder_Concrete_api_1.php');
        self::assertFileExists($this->cacheDirectory.'/serialize_Tests_Liip_Serializer_Fixtures_Builder_Concrete_api_2.php');
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

    public function testLaterWithClassReplacesEarlier(): void
    {
        $this->builder()
            ->withClass(Concrete::class, versions: ['1'])
            ->withClass(Concrete::class, versions: ['2'])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete_2'));
        self::assertFileDoesNotExist($this->file('serialize', 'Concrete_1'));
    }

    public function testLaterWithClassReplacesAllFields(): void
    {
        $this->builder()
            ->withClass(Concrete::class, versions: ['1'], groupCombinations: [new GroupCombination(['api'])])
            ->withClass(Concrete::class, versions: ['2'])
            ->withClasses([Concrete::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete_2'));
        self::assertFileDoesNotExist($this->file('serialize', 'Concrete_api_2'));
        self::assertFileDoesNotExist($this->file('serialize', 'Concrete_api_1'));
    }

    public function testPlainListAfterWithClassKeepsConfig(): void
    {
        $this->builder()
            ->withClass(Concrete::class, versions: ['2'])
            ->withSerializerClasses([Concrete::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete_2'));
        self::assertFileDoesNotExist($this->file('serialize', 'Concrete'));
    }

    public function testWithClassTarget(): void
    {
        $this->builder()
            ->withClass(Concrete::class, target: GenerationTarget::Serializer)
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete'));
        self::assertFileDoesNotExist($this->file('deserialize', 'Concrete'));
    }

    public function testLaterWithClassReplacesTarget(): void
    {
        $this->builder()
            ->withClass(Concrete::class)
            ->withClass(Concrete::class, target: GenerationTarget::Serializer)
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete'));
        self::assertFileDoesNotExist($this->file('deserialize', 'Concrete'));
    }

    public function testLaterWithClassKeepsDeserializerFromPlainList(): void
    {
        $this->builder()
            ->withDeserializerClasses([Concrete::class])
            ->withClass(Concrete::class)
            ->withClass(Concrete::class, target: GenerationTarget::Serializer)
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('deserialize', 'Concrete'));
    }

    public function testLeadingBackslashOfClassNamesIsIgnored(): void
    {
        $this->builder()
            ->withClass('\\'.Concrete::class, versions: ['1'])
            ->withClass(Concrete::class, versions: ['2'])
            ->withSerializerClasses(['\\'.Nested::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete_2'));
        self::assertFileDoesNotExist($this->file('serialize', 'Concrete_1'));
        self::assertFileExists($this->file('serialize', 'Sub_Nested'));
    }

    /**
     * @param \Closure(SerializerConfigBuilder): mixed $call
     */
    #[DataProvider('provideRejectsInvalidConfigurationCases')]
    public function testRejectsInvalidConfiguration(\Closure $call): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $call(SerializerConfig::configure($this->cacheDirectory));
    }

    /**
     * @return iterable<string, array{\Closure(SerializerConfigBuilder): mixed}>
     */
    public static function provideRejectsInvalidConfigurationCases(): iterable
    {
        yield 'empty default versions' => [static fn (SerializerConfigBuilder $b): SerializerConfigBuilder => $b->withDefaultVersions([])];
        yield 'empty default group combinations' => [static fn (SerializerConfigBuilder $b): SerializerConfigBuilder => $b->withDefaultGroupCombinations([])];
        yield 'empty class versions' => [static fn (SerializerConfigBuilder $b): SerializerConfigBuilder => $b->withClass(Concrete::class, versions: [])];
        yield 'empty class group combinations' => [static fn (SerializerConfigBuilder $b): SerializerConfigBuilder => $b->withClass(Concrete::class, groupCombinations: [])];
        yield 'class group combinations without objects' => [static fn (SerializerConfigBuilder $b): SerializerConfigBuilder => $b->withClass(Concrete::class, groupCombinations: [[]])];
    }

    public function testListMethodsAppend(): void
    {
        $this->builder()
            ->withClasses([Concrete::class])
            ->withClasses([Nested::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete'));
        self::assertFileExists($this->file('serialize', 'Sub_Nested'));
    }

    public function testSameClassInSeveralListsGeneratesBothSides(): void
    {
        $this->builder()
            ->withClasses([Concrete::class])
            ->withSerializerClasses([Concrete::class])
            ->withDeserializerClasses([Concrete::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->file('serialize', 'Concrete'));
        self::assertFileExists($this->file('deserialize', 'Concrete'));
    }

    private function builder(string $subDirectory = ''): SerializerConfigBuilder
    {
        return SerializerConfig::configure($this->cacheDirectory.$subDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
        ;
    }

    private function file(string $type, string $name): string
    {
        return $this->cacheDirectory.'/'.$type.'_Tests_Liip_Serializer_Fixtures_Builder_'.$name.'.php';
    }

    private static function createMetadataBuilder(): Builder
    {
        return new Builder(
            new Parser([new ReflectionParser(), new PhpDocParser(strict: false), new JMSParser(new AnnotationReader())]),
            new RecursionChecker(null, []),
        );
    }
}
