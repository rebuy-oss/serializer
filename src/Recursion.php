<?php

declare(strict_types=1);

namespace Liip\Serializer;

use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeIterable;

/**
 * @internal
 */
final class Recursion
{
    private function __construct()
    {
    }

    /**
     * @param array<string, positive-int> $stack
     */
    public static function hasMaxDepthReached(PropertyMetadata $propertyMetadata, array $stack): bool
    {
        if (null === $propertyMetadata->getMaxDepth()) {
            return false;
        }

        $className = self::getClassNameFromProperty($propertyMetadata);
        if (null === $className) {
            return false;
        }

        $classStackCount = $stack[$className] ?? 0;

        return $classStackCount > $propertyMetadata->getMaxDepth();
    }

    private static function getClassNameFromProperty(PropertyMetadata $propertyMetadata): ?string
    {
        $type = $propertyMetadata->getType();
        if ($type instanceof PropertyTypeIterable) {
            $type = $type->getLeafType();
        }

        if (!$type instanceof PropertyTypeClass) {
            return null;
        }

        return $type->getClassName();
    }
}
