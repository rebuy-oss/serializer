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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\Liip\Serializer\Fixtures\ListModel;

#[Revs(20)]
#[Warmup(2)]
#[Iterations(5)]
#[BeforeMethods(['setUp'])]
#[OutputTimeUnit('milliseconds')]
#[ParamProviders(['provideSubscriberCounts'])]
final class EventDispatcherBench
{
    private Serializer $serializer;

    private ListModel $model;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * @param array{subscribers: int|null, unrelatedSubscribers: int} $params
     */
    public function setUp(array $params): void
    {
        BenchmarkFactory::generateFiles();

        $dispatcher = null;
        if (null !== $params['subscribers']) {
            $dispatcher = new EventDispatcher();
            for ($i = 0; $i < $params['subscribers']; ++$i) {
                $dispatcher->addSubscriber(new NoopEventSubscriber());
            }
            for ($i = 0; $i < $params['unrelatedSubscribers']; ++$i) {
                $dispatcher->addSubscriber(new UnrelatedEventSubscriber());
            }
        }

        $this->serializer = new Serializer(BenchmarkFactory::cacheDir(), $dispatcher);
        $this->model = BenchmarkFactory::createSmallModel();
        $this->data = $this->serializer->toArray($this->model);
    }

    /**
     * @return \Generator<string, array{subscribers: int|null, unrelatedSubscribers: int}>
     */
    public function provideSubscriberCounts(): \Generator
    {
        yield 'no dispatcher' => ['subscribers' => null, 'unrelatedSubscribers' => 0];
        yield 'no subscriber' => ['subscribers' => 0, 'unrelatedSubscribers' => 0];
        yield 'one subscriber' => ['subscribers' => 1, 'unrelatedSubscribers' => 0];
        yield 'hundred subscribers' => ['subscribers' => 100, 'unrelatedSubscribers' => 0];
        yield 'five subscribers, 95 unrelated' => ['subscribers' => 5, 'unrelatedSubscribers' => 95];
    }

    public function benchManySmallToArray(): void
    {
        for ($i = 0; $i < 1000; ++$i) {
            $this->serializer->toArray($this->model);
        }
    }

    public function benchManySmallFromArray(): void
    {
        for ($i = 0; $i < 1000; ++$i) {
            $this->serializer->fromArray($this->data, ListModel::class);
        }
    }
}
