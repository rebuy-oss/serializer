<?php

declare(strict_types=1);

namespace Liip\Serializer;

use Liip\MetadataParser\Metadata\ClassMetadata;
use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeUnion;

/**
 * @internal
 */
final readonly class ExclusionResolver
{
    public function __construct(
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function excludeIf(ClassMetadata|PropertyMetadata $metadata): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        return $metadata->getExcludeIf();
    }

    /**
     * @param PropertyType<*> $type
     */
    public function typeMayBeExcluded(PropertyType $type): bool
    {
        if ($type instanceof PropertyTypeUnion) {
            return array_any($type->getTypes(), $this->typeMayBeExcluded(...));
        }

        if (!$type instanceof PropertyTypeClass) {
            return false;
        }

        return $this->classMayBeExcluded($type->getClassMetadata());
    }

    public function classMayBeExcluded(ClassMetadata $classMetadata): bool
    {
        if (null !== $this->excludeIf($classMetadata)) {
            return true;
        }

        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();
        if (null === $discriminatorMetadata || $discriminatorMetadata->baseClass !== $classMetadata->getClassName()) {
            return false;
        }

        return array_any(
            $discriminatorMetadata->getClassMetadataList(),
            fn (ClassMetadata $subClassMetadata): bool => null !== $this->excludeIf($subClassMetadata),
        );
    }
}
