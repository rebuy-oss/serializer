<?php

declare(strict_types=1);

namespace Liip\Serializer;

use Liip\MetadataParser\Builder;
use Liip\MetadataParser\Metadata\AbstractPropertyType;
use Liip\MetadataParser\Metadata\ClassMetadata;
use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\Metadata\PropertyTypeEnum;
use Liip\MetadataParser\Metadata\PropertyTypeIterable;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnion;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\Reducer\GroupReducer;
use Liip\MetadataParser\Reducer\PreferredReducer;
use Liip\MetadataParser\Reducer\TakeBestReducer;
use Liip\MetadataParser\Reducer\VersionReducer;
use Liip\Serializer\Configuration\GeneratorConfiguration;
use Liip\Serializer\Path\ModelPath;
use Liip\Serializer\Template\Serialization;
use Symfony\Component\Filesystem\Filesystem;

final class SerializerGenerator
{
    private const FILENAME_PREFIX = 'serialize';

    private Filesystem $filesystem;

    public function __construct(
        private Serialization $templating,
        private GeneratorConfiguration $configuration,
        private string $cacheDirectory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @param list<string> $serializerGroups
     */
    public static function buildSerializerFunctionName(string $className, ?string $apiVersion, array $serializerGroups): string
    {
        $functionName = self::FILENAME_PREFIX.'_'.$className;
        if (\count($serializerGroups)) {
            $functionName .= '_'.implode('_', $serializerGroups);
        }
        if (null !== $apiVersion) {
            $functionName .= '_'.$apiVersion;
        }

        return preg_replace('/[^a-zA-Z0-9_]/', '_', $functionName);
    }

    public function generate(Builder $metadataBuilder): void
    {
        $this->filesystem->mkdir($this->cacheDirectory);

        foreach ($this->configuration as $classToGenerate) {
            foreach ($classToGenerate as $groupCombination) {
                $className = $classToGenerate->getClassName();
                foreach ($groupCombination->getVersions() as $version) {
                    $groups = $groupCombination->getGroups();
                    if ('' === $version) {
                        if ([] === $groups) {
                            $metadata = $metadataBuilder->build($className, [
                                new PreferredReducer(),
                                new TakeBestReducer(),
                            ]);
                            $this->writeFile($className, null, [], $metadata);
                        } else {
                            $metadata = $metadataBuilder->build($className, [
                                new GroupReducer($groups),
                                new PreferredReducer(),
                                new TakeBestReducer(),
                            ]);
                            $this->writeFile($className, null, $groups, $metadata);
                        }
                    } else {
                        $metadata = $metadataBuilder->build($className, [
                            new VersionReducer($version),
                            new GroupReducer($groups),
                            new TakeBestReducer(),
                        ]);
                        $this->writeFile($className, $version, $groups, $metadata);
                    }
                }
            }
        }
    }

    /**
     * @param list<string> $serializerGroups
     */
    private function writeFile(
        string $className,
        ?string $apiVersion,
        array $serializerGroups,
        ClassMetadata $classMetadata,
    ): void {
        $functionName = self::buildSerializerFunctionName($className, $apiVersion, $serializerGroups);

        $arrayPath = Serialization::varJsonPath();
        $modelPath = Serialization::varModel();
        $code = $this->generateCodeForClass($classMetadata, $arrayPath, $modelPath);
        $code = $this->templating->renderFunction($functionName, $className, $code);

        $this->filesystem->dumpFile(\sprintf('%s/%s.php', $this->cacheDirectory, $functionName), $code);
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForClass(
        ClassMetadata $classMetadata,
        ModelPath $target,
        ModelPath $modelPath,
        array $stack = [],
        int $depth = 0,
    ): string {
        $className = $classMetadata->getClassName();
        $handler = $this->configuration->findSerializerHandlerForClass($className);
        if (null !== $handler) {
            return $this->templating->renderAssign($target, $handler->generateSerializeExpression($className, "{$modelPath}"));
        }

        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();
        $properties = $classMetadata->getProperties();
        if ($discriminatorMetadata && $discriminatorMetadata->baseClass == $className) {
            return $this->generateCodeForDiscriminatorClass($classMetadata, $target, $modelPath, $stack, $depth);
        }

        $stack[$className] = ($stack[$className] ?? 0) + 1;

        $isRootLevel = 0 === $depth;
        $nestedTarget = $isRootLevel ? $target : ModelPath::inventVariable("{$target}", 'object');

        $code = '';
        $initialValues = [];
        if ($discriminatorMetadata) {
            $initialValues[$discriminatorMetadata->propertyName] = [
                'key' => var_export($discriminatorMetadata->propertyName, true),
                'value' => var_export($discriminatorMetadata->value, true),
            ];
        }
        if ($this->configuration->shouldSerializeNull()) {
            foreach ($properties as $key => $property) {
                $type = $property->getType();
                $modelPropertyPath = $property->getAccessor()->hasGetterMethod()
                    ? $modelPath->withPath($property->getAccessor()->getGetterMethod().'()')
                    : $modelPath->withPath($property->getName());
                $expression = $this->generateCodeForFieldTypeValue($type, $modelPropertyPath);
                if (null === $expression || $this->fieldTypeRequiresExplicitNullSet($type)) {
                    continue;
                }

                $serializedName = $property->getSerializedName();
                $initialValues[$serializedName] = [
                    'key' => var_export($serializedName, true),
                    'value' => $expression,
                ];
                unset($properties[$key]);
            }
        }
        foreach ($properties as $propertyMetadata) {
            $code .= $this->generateCodeForField($propertyMetadata, $nestedTarget, $modelPath, $stack);
        }

        $mightBeEmpty = !$this->configuration->shouldSerializeNull() || !$classMetadata->getProperties();
        $built = $this->templating->renderClass($nestedTarget, $code, initialValues: $initialValues, withEmptyObject: $mightBeEmpty);
        if ($isRootLevel) {
            return $built;
        }

        return $built.$this->templating->renderAssign($target, "{$nestedTarget}");
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForDiscriminatorClass(
        ClassMetadata $classMetadata,
        ModelPath $target,
        ModelPath $modelPath,
        array $stack = [],
        int $depth = 0,
    ): string {
        $code = '';
        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();
        foreach ($discriminatorMetadata->classMap as $class) {
            $innerCode = $this->generateCodeForClass($discriminatorMetadata->getMetadataForClass($class), $target, $modelPath, $stack, $depth);
            $code .= $this->templating->renderInstanceOfConditional($modelPath, $class, $innerCode);
        }

        return $code;
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForField(
        PropertyMetadata $propertyMetadata,
        ModelPath $target,
        ModelPath $modelPath,
        array $stack,
        int $depth = 0,
    ): string {
        if (Recursion::hasMaxDepthReached($propertyMetadata, $stack)) {
            return '';
        }

        $modelPropertyPath = $modelPath.'->'.$propertyMetadata->getName();
        $fieldTarget = $target->withArrayLiteral($propertyMetadata->getSerializedName());
        $modelProperty = $modelPath->withPath($propertyMetadata->getName());
        $type = $propertyMetadata->getType();
        $requiresExplicitNullSet = $this->fieldTypeRequiresExplicitNullSet($type);
        $shouldSerializeNull = $this->configuration->shouldSerializeNull();
        $setNull = $shouldSerializeNull ? $this->templating->renderAssign($fieldTarget, 'null') : null;
        // The conditionals are null checks, so expressions under one can assume non-null types
        $nonNullType = ($type instanceof AbstractPropertyType) ? $type->asNullable(false) : $type;

        if ($propertyMetadata->getAccessor()->hasGetterMethod()) {
            $tempVariable = ModelPath::tempVariable([(string) $modelPath, ucfirst($propertyMetadata->getName())]);
            $value = $modelPath->withPath($propertyMetadata->getAccessor()->getGetterMethod().'()');

            if ($shouldSerializeNull && !$requiresExplicitNullSet) {
                return $this->generateCodeForFieldType($type, $fieldTarget, $value, $stack)."\n";
            }

            return $this->templating->renderConditional(
                $this->templating->renderTempVariable($tempVariable, "{$value}"),
                $this->generateCodeForFieldType($nonNullType, $fieldTarget, $tempVariable, $stack, $depth + 1),
                $setNull
            );
        }

        if (!$propertyMetadata->isPublic()) {
            throw new \Exception(\sprintf('Property %s is not public and no getter has been defined. Stack %s', $modelProperty, var_export($stack, true)));
        }

        if ($shouldSerializeNull && !$requiresExplicitNullSet) {
            return $this->generateCodeForFieldType($type, $fieldTarget, $modelProperty, $stack)."\n";
        }

        // thanks to the `if ($shouldSerializeNull && !$requiresExplicitNullSet)`, we know we need an if-else to contain the 2 cases (null and non-null).
        $serializeField = $this->generateCodeForFieldType($nonNullType, $fieldTarget, $modelProperty, $stack);

        return $this->templating->renderConditional((string) $modelProperty, $serializeField, $shouldSerializeNull ? $setNull : null);
    }

    /**
     * Whether a PropertyType requires the target to be set to null in a separate statement (returns true), or its expression already allows the null case (returns false)
     *
     * @return bool True if the type needs a separate statement for the null case
     */
    private function fieldTypeRequiresExplicitNullSet(PropertyType $type): bool
    {
        if (!$type->isNullable()) {
            return false;
        }

        switch ($type) {
            case $type instanceof PropertyTypePrimitive:
            case $type instanceof PropertyTypeUnknown:
            case $type instanceof PropertyTypeDateTime: // can use the null-check operator
            case $type instanceof PropertyTypeEnum: // can use the null-check operator
                return false;

            case $type instanceof PropertyTypeClass:
            case $type instanceof PropertyTypeIterable:
                return true;

            case $type instanceof PropertyTypeUnion:
        }

        return true;
    }

    private function generateCodeForFieldTypeValue(PropertyType $type, ModelPath $modelPropertyPath): ?string
    {
        switch ($type) {
            case $type instanceof PropertyTypeDateTime:
                $dateFormat = $type->getFormat() ?: \DateTimeInterface::ISO8601;

                return $this->templating->renderDateTime((string) $modelPropertyPath, $dateFormat, $type->isNullable());

            case $type instanceof PropertyTypePrimitive:
            case $type instanceof PropertyTypeUnknown:
                // for arrays of scalars, copy the field even when it's an empty array
                return (string) $modelPropertyPath;

            case $type instanceof PropertyTypeEnum:
                $valueAccess = $type->shouldSerializeAsValue() ? 'value' : 'name';

                return (string) $modelPropertyPath->withPath($valueAccess, $type->isNullable());

            case $type instanceof PropertyTypeIterable:
                $leafType = $type->getLeafType();
                if (($leafType instanceof PropertyTypePrimitive) || ($this->configuration->shouldAllowGenericArrays() && ($leafType instanceof PropertyTypeUnknown))) {
                    return (string) $modelPropertyPath;
                }

                return null;
        }

        return null;
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForFieldType(
        PropertyType $type,
        ModelPath $target,
        ModelPath $modelPropertyPath,
        array $stack,
        int $depth = 0,
    ): string {
        switch ($type) {
            case $type instanceof PropertyTypeDateTime:
            case $type instanceof PropertyTypePrimitive:
            case $type instanceof PropertyTypeUnknown:
            case $type instanceof PropertyTypeEnum:
                // for arrays of scalars, copy the field even when it's an empty array
                return $this->templating->renderAssign($target, $this->generateCodeForFieldTypeValue($type, $modelPropertyPath));

            case $type instanceof PropertyTypeClass:
                return $this->generateCodeForClass($type->getClassMetadata(), $target, $modelPropertyPath, $stack, $depth);

            case $type instanceof PropertyTypeIterable:
                return $this->generateCodeForArray($type, $target, $modelPropertyPath, $stack, $depth);

            case $type instanceof PropertyTypeUnion:
                return $this->generateCodeForUnion($type, $target, $modelPropertyPath, $stack, $depth);

            default:
                throw new \Exception('Unexpected type '.$type::class.' at '.$modelPropertyPath);
        }
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForArray(
        PropertyTypeIterable $type,
        ModelPath $target,
        ModelPath $modelPath,
        array $stack,
        int $depth = 0,
    ): string {
        $index = ModelPath::indexVariable("{$target}");
        $arrayVar = ModelPath::inventVariable("{$target}", 'array');
        $value = ModelPath::inventVariable("{$target}", 'value');

        $subType = $type->getSubType();
        $listTarget = $arrayVar;
        $itemTarget = $listTarget->withArray("{$index}");

        if ($subType instanceof PropertyTypeUnknown && $this->configuration->shouldAllowGenericArrays()) {
            return $this->templating->renderArrayAssign($target, "{$modelPath}");
        }

        if ($subType instanceof PropertyTypePrimitive) {
            return $this->renderLastArray($type, "{$target}", "{$modelPath}");
        }

        switch ($subType) {
            case $subType instanceof PropertyTypeIterable:
                $innerCode = $this->generateCodeForArray($subType, $itemTarget, $value, $stack, $depth + 1);
                break;

            case $subType instanceof PropertyTypeEnum:
                $innerCode = $this->generateCodeForFieldType($subType, $itemTarget, $value, $stack, $depth + 1);
                break;

            case $subType instanceof PropertyTypeClass:
                $innerCode = $this->generateCodeForClass($subType->getClassMetadata(), $itemTarget, $value, $stack, $depth + 1);
                break;

            default:
                throw new \Exception('Unexpected array subtype '.$subType::class);
        }

        if ('' === $innerCode) {
            if ($type->isHashmap()) {
                return $this->templating->renderLoopHashmapEmpty($target);
            }

            return $this->templating->renderLoopArrayEmpty($target);
        }

        $loop = $this->templating->renderLoopArray($listTarget, $modelPath, $index, $value, $innerCode);

        return $loop.$this->renderLastArray($type, "{$target}", "{$listTarget}");
    }

    private function renderLastArray(PropertyTypeIterable $type, string $target, string $listTarget): string
    {
        if ($type->isHashmap()) {
            return $this->templating->renderHashmap($target, $listTarget);
        }

        return $this->templating->renderArrayAssign($target, $listTarget);
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForUnion(
        PropertyTypeUnion $subType,
        ModelPath $target,
        ModelPath $modelPath,
        array $stack,
        int $depth = 0,
    ): string {
        $code = '';

        $types = $subType->getTypes();
        $typesWithoutPrimitives = array_filter($types, static function (PropertyType $subType): bool {
            return !($subType instanceof PropertyTypePrimitive || $subType instanceof PropertyTypeUnknown);
        });

        $hasPrimitives = \count($types) !== \count($typesWithoutPrimitives);
        if ($hasPrimitives) {
            $innerCode = $this->templating->renderAssign("{$target}", "{$modelPath}");
            $code .= $this->templating->renderPrimitiveConditional($modelPath, $innerCode);
        }

        foreach ($typesWithoutPrimitives as $subType) {
            switch ($subType::class) {
                case PropertyTypeClass::class:
                    $innerCode = $this->generateCodeForFieldType($subType, $target, $modelPath, $stack, $depth);
                    $code .= $this->templating->renderInstanceOfConditional($modelPath, $subType->getClassName(), $innerCode);
                    break;
                case PropertyTypeIterable::class:
                    $innerCode = $this->generateCodeForArray($subType, $target, $modelPath, $stack, $depth);
                    $code .= $this->templating->renderArrayConditional($modelPath, $innerCode);
                    break;
            }
        }

        return $code;
    }

    public static function isPrimitive(mixed $data): bool
    {
        if (\is_array($data)) {
            return false;
        }

        return null === $data || \is_scalar($data);
    }

    /**
     * @phpstan-ignore method.unused
     */
    private static function isArrayForPrimitive(PropertyTypeIterable $type): bool
    {
        do {
            $type = $type->getSubType();
            if ($type instanceof PropertyTypePrimitive) {
                return true;
            }
        } while ($type instanceof PropertyTypeIterable);

        return false;
    }
}
