<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

final readonly class GroupCombination
{
    /**
     * @var list<string>
     */
    private array $groups;

    /**
     * @var list<string>|null
     */
    private ?array $versions;

    /**
     * @param list<string>          $groups   One combination of groups to generate. An empty list means to generate with no groups.
     * @param list<string|int>|null $versions Versions to generate for this combination. An empty string '' means to generate
     *                                        without a version. Null means to use the versions of the class.
     */
    public function __construct(array $groups, ?array $versions = null)
    {
        if (null !== $versions && [] === $versions) {
            throw new \InvalidArgumentException('Version list may not be empty. To generate without version, specify an empty string. To use the default versions, pass null.');
        }

        sort($groups);
        $this->groups = $groups;
        $this->versions = null === $versions ? null : array_map(strval(...), $versions);
    }

    /**
     * @return list<string>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return list<string>|null
     */
    public function getVersions(): ?array
    {
        return $this->versions;
    }
}
