<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

final readonly class ClassToGenerate
{
    /**
     * @param class-string           $className
     * @param list<string>           $versions
     * @param list<GroupCombination> $groupCombinations
     */
    public function __construct(
        private string $className,
        private array $versions,
        private array $groupCombinations,
    ) {
    }

    /**
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return list<GroupCombination>
     */
    public function getGroupCombinations(): array
    {
        return $this->groupCombinations;
    }

    /**
     * @return list<string>
     */
    public function getVersions(GroupCombination $groupCombination): array
    {
        return $groupCombination->getVersions() ?? $this->versions;
    }
}
