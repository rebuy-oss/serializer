<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Performance;

use Liip\Serializer\Serializer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Tests\Liip\Serializer\Fixtures\ListModel;

#[Revs(20)]
#[Warmup(2)]
#[Iterations(5)]
#[BeforeMethods(['setUp'])]
#[OutputTimeUnit('milliseconds')]
final class SerializationBench
{
    private Serializer $serializer;

    private ListModel $model;

    private ListModel $smallModel;

    public function setUp(): void
    {
        BenchmarkFactory::generateFiles();

        $this->serializer = BenchmarkFactory::createSerializer();
        $this->model = BenchmarkFactory::createLargeModel();
        $this->smallModel = BenchmarkFactory::createSmallModel();
    }

    public function benchToArray(): void
    {
        $this->serializer->toArray($this->model);
    }

    public function benchSerialize(): void
    {
        $this->serializer->serialize($this->model, 'json');
    }

    /**
     * Serialize many small objects of the same type to exercise the per-call dispatch overhead.
     */
    public function benchManySmall(): void
    {
        for ($i = 0; $i < 1000; ++$i) {
            $this->serializer->toArray($this->smallModel);
        }
    }
}
