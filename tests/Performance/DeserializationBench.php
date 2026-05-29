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
final class DeserializationBench
{
    private Serializer $serializer;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    private string $json;

    public function setUp(): void
    {
        BenchmarkFactory::generateFiles();

        $this->serializer = BenchmarkFactory::createSerializer();
        $this->data = BenchmarkFactory::createLargeArray();
        $this->json = json_encode($this->data, \JSON_THROW_ON_ERROR);
    }

    public function benchFromArray(): void
    {
        $this->serializer->fromArray($this->data, ListModel::class);
    }

    public function benchDeserialize(): void
    {
        $this->serializer->deserialize($this->json, ListModel::class, 'json');
    }
}
