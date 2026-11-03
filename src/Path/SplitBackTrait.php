<?php

declare(strict_types=1);

namespace Liip\Serializer\Path;

/**
 * @internal
 */
trait SplitBackTrait
{
    /**
     * Split a path into a base and the n steps at the end of its path.
     *
     * @param positive-int $steps Number of steps to remove
     *
     * @return array{0: self, 1: non-empty-list<AbstractEntry>} First element is the stubbed path, second element is a list of the last n steps
     *
     * @throws \OutOfRangeException if the $steps argument is larger than there are steps in this path
     */
    public function splitBack(int $steps = 1): array
    {
        $root = clone $this;
        $rest = [];

        for (; 0 < $steps; --$steps) {
            $piece = array_pop($root->path);
            if (null === $piece || $piece instanceof Root) {
                throw new \OutOfRangeException('Not enough steps to split');
            }
            $rest[] = $piece;
        }

        return [$root, $rest];
    }
}
