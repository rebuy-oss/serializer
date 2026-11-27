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
use Liip\Serializer\Configuration\GroupCombination;
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
        $serializer = SerializerConfigBuilder::create($this->cacheDirectory)
            ->withClasses(['Does\Not\Exist'])
            ->buildSerializer()
        ;

        self::assertInstanceOf(Serializer::class, $serializer);
    }

    public function testBuildCompilerWithoutMetadataBuilderThrows(): void
    {
        $this->expectException(\LogicException::class);

        SerializerConfigBuilder::create($this->cacheDirectory)
            ->withClasses([Concrete::class])
            ->buildCompiler()
        ;
    }

    public function testWithClassesGeneratesBothSides(): void
    {
        $this->createBuilder()
            ->withClasses([Concrete::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete'));
        self::assertFileExists($this->getFilePath('deserialize', 'Concrete'));
    }

    public function testSerializerOnlyAndDeserializerOnlyClasses(): void
    {
        $this->createBuilder()
            ->withClassesForSerializer([Concrete::class], ['1'], [new GroupCombination(['api'])])
            ->withClassesForDeserializer([Nested::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete_api_1'));
        self::assertFileExists($this->getFilePath('deserialize', 'Sub_Nested'));
        self::assertFileDoesNotExist($this->getFilePath('deserialize', 'Concrete'));
        self::assertFileDoesNotExist($this->getFilePath('serialize', 'Sub_Nested'));
    }

    public function testWithClassesUsesVersionsAndGroupCombinations(): void
    {
        $this->createBuilder()
            ->withClasses([Concrete::class], ['1', '2'], [new GroupCombination(['api'])])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete_api_1'));
        self::assertFileExists($this->getFilePath('serialize', 'Concrete_api_2'));
    }

    public function testExpressionLanguageIsUsedInGeneratedCode(): void
    {
        $withDirectory = $this->cacheDirectory.'/with';
        SerializerConfigBuilder::create($withDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->withClassesForSerializer([ExcludeIfModel::class])
            ->withExpressionLanguage(new ExpressionLanguage())
            ->buildCompiler()
            ->compile()
        ;

        $withoutDirectory = $this->cacheDirectory.'/without';
        SerializerConfigBuilder::create($withoutDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
            ->withClassesForSerializer([ExcludeIfModel::class])
            ->buildCompiler()
            ->compile()
        ;

        $file = '/serialize_Tests_Liip_Serializer_Fixtures_ExcludeIfModel.php';
        self::assertStringContainsString('expressionLanguage', (string) file_get_contents($withDirectory.$file));
        self::assertStringNotContainsString('expressionLanguage', (string) file_get_contents($withoutDirectory.$file));
    }

    public function testLaterWithClassReplacesEarlier(): void
    {
        $this->createBuilder()
            ->withClass(Concrete::class, versions: ['1'], groupCombinations: [new GroupCombination(['api'])])
            ->withClass(Concrete::class, versions: ['2'])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete_2'));
        self::assertFileDoesNotExist($this->getFilePath('serialize', 'Concrete_api_1'));
        self::assertFileDoesNotExist($this->getFilePath('serialize', 'Concrete_api_2'));
    }

    public function testWithClassForSerializer(): void
    {
        $this->createBuilder()
            ->withClassForSerializer(Concrete::class, versions: ['1'])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete_1'));
        self::assertFileDoesNotExist($this->getFilePath('deserialize', 'Concrete'));
    }

    public function testWithClassForDeserializer(): void
    {
        $this->createBuilder()
            ->withClassForDeserializer(Concrete::class)
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('deserialize', 'Concrete'));
        self::assertFileDoesNotExist($this->getFilePath('serialize', 'Concrete'));
    }

    public function testWithClassForSerializerKeepsDeserializer(): void
    {
        $this->createBuilder()
            ->withClass(Concrete::class)
            ->withClassForSerializer(Concrete::class, versions: ['2'])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete_2'));
        self::assertFileDoesNotExist($this->getFilePath('serialize', 'Concrete'));
        self::assertFileExists($this->getFilePath('deserialize', 'Concrete'));
    }

    public function testWithClassForDeserializerKeepsSerializer(): void
    {
        $this->createBuilder()
            ->withClassForSerializer(Concrete::class, versions: ['1'])
            ->withClassForDeserializer(Concrete::class)
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('deserialize', 'Concrete'));
        self::assertFileExists($this->getFilePath('serialize', 'Concrete_1'));
    }

    public function testLeadingBackslashOfClassNamesIsIgnored(): void
    {
        $this->createBuilder()
            ->withClass('\\'.Concrete::class, versions: ['1'])
            ->withClass(Concrete::class, versions: ['2'])
            ->withClassesForSerializer(['\\'.Nested::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete_2'));
        self::assertFileDoesNotExist($this->getFilePath('serialize', 'Concrete_1'));
        self::assertFileExists($this->getFilePath('serialize', 'Sub_Nested'));
    }

    /**
     * @param list<string>           $versions
     * @param list<GroupCombination> $groupCombinations
     */
    #[DataProvider('provideWithClassRejectsInvalidConfigurationCases')]
    public function testWithClassRejectsInvalidConfiguration(array $versions, array $groupCombinations): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SerializerConfigBuilder::create($this->cacheDirectory)
            ->withClass(Concrete::class, $versions, $groupCombinations)
        ;
    }

    /**
     * @return iterable<string, array{list<string>, list<mixed>}>
     */
    public static function provideWithClassRejectsInvalidConfigurationCases(): iterable
    {
        yield 'empty versions' => [[], [new GroupCombination([])]];
        yield 'empty group combinations' => [[''], []];
        yield 'group combinations without objects' => [[''], [[]]];
    }

    /**
     * @param list<string>           $versions
     * @param list<GroupCombination> $groupCombinations
     */
    #[DataProvider('provideWithClassForSerializerRejectsInvalidConfigurationCases')]
    public function testWithClassForSerializerRejectsInvalidConfiguration(array $versions, array $groupCombinations): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SerializerConfigBuilder::create($this->cacheDirectory)
            ->withClassForSerializer(Concrete::class, $versions, $groupCombinations)
        ;
    }

    /**
     * @return iterable<string, array{list<string>, list<mixed>}>
     */
    public static function provideWithClassForSerializerRejectsInvalidConfigurationCases(): iterable
    {
        yield 'empty versions' => [[], [new GroupCombination([])]];
        yield 'empty group combinations' => [[''], []];
        yield 'group combinations without objects' => [[''], [[]]];
    }

    public function testWithClassesAccumulatesAcrossCalls(): void
    {
        $this->createBuilder()
            ->withClasses([Concrete::class])
            ->withClasses([Nested::class])
            ->buildCompiler()
            ->compile()
        ;

        self::assertFileExists($this->getFilePath('serialize', 'Concrete'));
        self::assertFileExists($this->getFilePath('serialize', 'Sub_Nested'));
    }

    private function createBuilder(): SerializerConfigBuilder
    {
        return SerializerConfigBuilder::create($this->cacheDirectory)
            ->withMetadataBuilder(self::createMetadataBuilder())
        ;
    }

    private function getFilePath(string $type, string $name): string
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
