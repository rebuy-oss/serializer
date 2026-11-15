<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Unit\Configuration;

use Liip\Serializer\Configuration\ClassDiscovery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Tests\Liip\Serializer\Fixtures\Discovery\Concrete;
use Tests\Liip\Serializer\Fixtures\Discovery\Skipped\Hidden;
use Tests\Liip\Serializer\Fixtures\Discovery\Sub\Nested;

final class ClassDiscoveryTest extends TestCase
{
    private const string NS = 'Tests\Liip\Serializer\Fixtures\Discovery\\';
    private const string DIR = __DIR__.'/../../Fixtures/Discovery';

    public function testFindsConcreteClassesRecursively(): void
    {
        self::assertSame(
            [Concrete::class, Hidden::class, Nested::class],
            new ClassDiscovery()->discover(self::NS, self::DIR),
        );
    }

    public function testSkipsClassNames(): void
    {
        self::assertSame([Concrete::class, Nested::class], new ClassDiscovery([Hidden::class])->discover(self::NS, self::DIR));
    }

    public function testSkipsPathGlobs(): void
    {
        self::assertSame([Concrete::class, Nested::class], new ClassDiscovery([realpath(self::DIR).'/Skipped/*'])->discover(self::NS, self::DIR));
    }

    public function testNormalizesNamespaceAndDirectory(): void
    {
        self::assertSame(
            new ClassDiscovery()->discover(self::NS, self::DIR),
            new ClassDiscovery()->discover('\Tests\Liip\Serializer\Fixtures\Discovery', self::DIR.'/'),
        );
    }

    public function testThrowsWhenFileHasNoMatchingClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tests\Liip\Serializer\Fixtures\DiscoveryBroken\functions');
        new ClassDiscovery()->discover('Tests\Liip\Serializer\Fixtures\DiscoveryBroken\\', __DIR__.'/../../Fixtures/DiscoveryBroken');
    }

    public function testSkippedBrokenFileDoesNotThrow(): void
    {
        $directory = realpath(__DIR__.'/../../Fixtures/DiscoveryBroken');

        self::assertSame([], new ClassDiscovery([$directory.'/functions.php'])->discover('Tests\Liip\Serializer\Fixtures\DiscoveryBroken\\', $directory));
    }

    public function testThrowsWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(DirectoryNotFoundException::class);
        new ClassDiscovery()->discover(self::NS, self::DIR.'/Missing');
    }
}
