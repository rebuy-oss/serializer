<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final readonly class ClassDiscovery
{
    /**
     * @param list<string> $skip Class names or path globs (entries that contain "/") to exclude
     */
    public function __construct(private array $skip = [])
    {
    }

    /**
     * @return list<class-string>
     */
    public function discover(string $namespace, string $directory): array
    {
        $namespace = trim($namespace, '\\').'\\';
        $finder = new Finder()->files()->in(rtrim($directory, '/'))->name('*.php');

        $classes = [];
        foreach ($finder as $file) {
            $className = $namespace.str_replace('/', '\\', substr($file->getRelativePathname(), 0, -4));
            if ($this->isSkipped($className, $file)) {
                continue;
            }

            if (!class_exists($className) && !interface_exists($className, false) && !trait_exists($className, false)) {
                throw new \InvalidArgumentException(\sprintf('Expected to find class "%s" in file "%s". Check the namespace prefix "%s".', $className, $file->getRealPath(), $namespace));
            }

            $reflection = new \ReflectionClass($className);
            if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum() || $reflection->isAbstract()) {
                continue;
            }

            $classes[] = $className;
        }

        sort($classes);

        return $classes;
    }

    private function isSkipped(string $className, SplFileInfo $file): bool
    {
        foreach ($this->skip as $entry) {
            if (str_contains($entry, '/')) {
                if (fnmatch($entry, $file->getRealPath())) {
                    return true;
                }

                continue;
            }

            if ($entry === $className) {
                return true;
            }
        }

        return false;
    }
}
