<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Performance;

use Liip\Serializer\Serializer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Tests\Liip\Serializer\Fixtures\ExcludeIfModel;

#[Revs(20)]
#[Warmup(2)]
#[Iterations(5)]
#[BeforeMethods(['setUp'])]
#[OutputTimeUnit('milliseconds')]
#[ParamProviders(['provideModelSizes', 'provideExpressionLanguage'])]
final class ExpressionLanguageBench
{
    private Serializer $serializer;

    private ExcludeIfModel $model;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * @param array{listSize: int, hashmapSize: int, expressionLanguage: 'default'|'unserialized cache'|null} $params
     */
    public function setUp(array $params): void
    {
        BenchmarkFactory::generateFiles();

        $this->model = BenchmarkFactory::createExcludeIfModel($params['listSize'], $params['hashmapSize']);
        $this->data = BenchmarkFactory::createSerializer()->toArray($this->model);

        $expressionLanguage = match ($params['expressionLanguage']) {
            null => null,
            'default' => new ExpressionLanguage(),
            'unserialized cache' => new ExpressionLanguage(new ArrayAdapter(deepClone: false)),
        };
        $this->serializer = new Serializer(BenchmarkFactory::cacheDir(), expressionLanguage: $expressionLanguage);
    }

    /**
     * The model has 6 expressions outside of the list and the hashmap, so the sizes give 50 and 2206 evaluations.
     *
     * @return \Generator<string, array{listSize: int, hashmapSize: int}>
     */
    public function provideModelSizes(): \Generator
    {
        yield 'small' => ['listSize' => 40, 'hashmapSize' => 4];
        yield 'large' => ['listSize' => 2000, 'hashmapSize' => 200];
    }

    /**
     * @return \Generator<string, array{expressionLanguage: 'default'|'unserialized cache'|null}>
     */
    public function provideExpressionLanguage(): \Generator
    {
        yield 'without expression language' => ['expressionLanguage' => null];
        yield 'with expression language' => ['expressionLanguage' => 'default'];
        yield 'with unserialized cache' => ['expressionLanguage' => 'unserialized cache'];
    }

    public function benchToArray(): void
    {
        $this->serializer->toArray($this->model);
    }

    public function benchFromArray(): void
    {
        $this->serializer->fromArray($this->data, ExcludeIfModel::class);
    }
}
