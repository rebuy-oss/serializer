<?php

declare(strict_types=1);

namespace Liip\Serializer;

use Liip\MetadataParser\Builder;
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
use Liip\Serializer\Template\Serialization;
use Symfony\Component\Filesystem\Filesystem;

final readonly class SerializerGenerator
{
    private const string FILENAME_PREFIX = 'serialize';

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

        $code = $this->templating->renderFunction(
            $functionName,
            $className,
            $this->generateCodeForClass($classMetadata, $apiVersion, $serializerGroups, '$jsonData', '$model')
        );

        $this->filesystem->dumpFile(\sprintf('%s/%s.php', $this->cacheDirectory, $functionName), $code);
    }

    /**
     * @param list<string>                $serializerGroups
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForClass(
        ClassMetadata $classMetadata,
        ?string $apiVersion,
        array $serializerGroups,
        string $target,
        string $modelPath,
        array $stack = [],
        int $depth = 0,
    ): string {
        $className = $classMetadata->getClassName();
        $handler = $this->configuration->findSerializerHandlerForClass($className);
        if (null !== $handler) {
            return $this->templating->renderAssign($target, $handler->generateSerializeExpression($className, $modelPath));
        }

        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();
        if (null !== $discriminatorMetadata && $discriminatorMetadata->baseClass == $className) {
            return $this->generateCodeForDiscriminatorClass($classMetadata, $apiVersion, $serializerGroups, $target, $modelPath, $stack, $depth);
        }

        $stack[$className] = ($stack[$className] ?? 0) + 1;

        $isRootLevel = 0 === $depth;
        $nestedTarget = $isRootLevel ? $target : '$object'.$depth;

        $code = '';
        foreach ($classMetadata->getProperties() as $propertyMetadata) {
            $code .= $this->generateCodeForField($propertyMetadata, $apiVersion, $serializerGroups, $nestedTarget, $modelPath, $stack, $depth);
        }

        if (null !== $discriminatorMetadata) {
            $discriminatorFieldTarget = $nestedTarget.'["'.$discriminatorMetadata->propertyName.'"]';
            $code .= $this->templating->renderAssign($discriminatorFieldTarget, \sprintf("'%s'", $discriminatorMetadata->value));
        }

        $built = $this->templating->renderClass($nestedTarget, $code);
        if ($isRootLevel) {
            return $built;
        }

        return $built.$this->templating->renderAssign($target, $nestedTarget);
    }

    /**
     * @param list<string>                $serializerGroups
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForDiscriminatorClass(
        ClassMetadata $classMetadata,
        ?string $apiVersion,
        array $serializerGroups,
        string $target,
        string $modelPath,
        array $stack = [],
        int $depth = 0,
    ): string {
        $code = '';
        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();
        foreach ($discriminatorMetadata->classMap as $class) {
            $code .= $this->templating->renderInstanceOfConditional(
                $modelPath,
                $class,
                $this->generateCodeForClass($discriminatorMetadata->getMetadataForClass($class), $apiVersion, $serializerGroups, $target, $modelPath, $stack, $depth)
            );
        }

        return $code;
    }

    /**
     * @param list<string>                $serializerGroups
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForField(
        PropertyMetadata $propertyMetadata,
        ?string $apiVersion,
        array $serializerGroups,
        string $target,
        string $modelPath,
        array $stack,
        int $depth = 0,
    ): string {
        if (Recursion::hasMaxDepthReached($propertyMetadata, $stack)) {
            return '';
        }

        $modelPropertyPath = $modelPath.'->'.$propertyMetadata->getName();
        $fieldTarget = $target.'["'.$propertyMetadata->getSerializedName().'"]';

        if ($propertyMetadata->getAccessor()->hasGetterMethod()) {
            $tempVariable = str_replace(['->', '[', ']', '$'], '', $modelPath).ucfirst($propertyMetadata->getName());

            return $this->templating->renderConditional(
                $this->templating->renderTempVariable($tempVariable, $this->templating->renderGetter($modelPath, $propertyMetadata->getAccessor()->getGetterMethod())),
                $this->generateCodeForFieldType($propertyMetadata->getType(), $apiVersion, $serializerGroups, $fieldTarget, '$'.$tempVariable, $stack, $depth + 1)
            );
        }
        if (!$propertyMetadata->isPublic()) {
            throw new \Exception(\sprintf('Property %s is not public and no getter has been defined. Stack %s', $modelPropertyPath, var_export($stack, true)));
        }

        return $this->templating->renderConditional(
            $modelPropertyPath,
            $this->generateCodeForFieldType($propertyMetadata->getType(), $apiVersion, $serializerGroups, $fieldTarget, $modelPropertyPath, $stack, $depth + 1)
        );
    }

    /**
     * @param list<string>                $serializerGroups
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForFieldType(
        PropertyType $type,
        ?string $apiVersion,
        array $serializerGroups,
        string $target,
        string $modelPropertyPath,
        array $stack,
        int $depth = 0,
    ): string {
        switch ($type) {
            case $type instanceof PropertyTypeDateTime:
                $dateFormat = $type->getFormat() ?: \DateTimeInterface::ISO8601;

                return $this->templating->renderAssign(
                    $target,
                    $this->templating->renderDateTime($modelPropertyPath, $dateFormat)
                );

            case $type instanceof PropertyTypePrimitive:
            case $type instanceof PropertyTypeUnknown:
                // for arrays of scalars, copy the field even when its an empty array
                return $this->templating->renderAssign($target, $modelPropertyPath);

            case $type instanceof PropertyTypeEnum:
                $valueAccess = $type->shouldSerializeAsValue() ? '->value' : '->name';

                return $this->templating->renderAssign($target, $modelPropertyPath.$valueAccess);

            case $type instanceof PropertyTypeClass:
                return $this->generateCodeForClass($type->getClassMetadata(), $apiVersion, $serializerGroups, $target, $modelPropertyPath, $stack, $depth);

            case $type instanceof PropertyTypeIterable:
                return $this->generateCodeForArray($type, $apiVersion, $serializerGroups, $target, $modelPropertyPath, $stack, $depth);

            case $type instanceof PropertyTypeUnion:
                return $this->generateCodeForUnion($type, $apiVersion, $serializerGroups, $target, $modelPropertyPath, $stack, $depth);

            default:
                throw new \Exception('Unexpected type '.$type::class.' at '.$modelPropertyPath);
        }
    }

    /**
     * @param list<string>                $serializerGroups
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForArray(
        PropertyTypeIterable $type,
        ?string $apiVersion,
        array $serializerGroups,
        string $target,
        string $modelPath,
        array $stack,
        int $depth = 0,
    ): string {
        $index = '$index'.$depth;
        $value = '$value'.$depth;

        $subType = $type->getSubType();

        switch ($subType) {
            case $subType instanceof PropertyTypePrimitive:
            case $subType instanceof PropertyTypeIterable && self::isArrayForPrimitive($subType):
            case $subType instanceof PropertyTypeUnknown && $this->configuration->shouldAllowGenericArrays():
                return $this->templating->renderArrayAssign($target, $modelPath);
        }

        $listTarget = '$array'.$depth;
        $itemTarget = $listTarget.'['.$index.']';

        switch ($subType) {
            case $subType instanceof PropertyTypeIterable:
                $innerCode = $this->generateCodeForArray($subType, $apiVersion, $serializerGroups, $itemTarget, $value, $stack, $depth + 1);
                break;

            case $subType instanceof PropertyTypeEnum:
                $innerCode = $this->generateCodeForFieldType($subType, $apiVersion, $serializerGroups, $itemTarget, $value, $stack, $depth + 1);
                break;

            case $subType instanceof PropertyTypeClass:
                $innerCode = $this->generateCodeForClass($subType->getClassMetadata(), $apiVersion, $serializerGroups, $itemTarget, $value, $stack, $depth + 1);
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

        if ($type->isHashmap()) {
            $loop = $this->templating->renderLoopHashmap($listTarget, $modelPath, $index, $value, $innerCode);
        } else {
            $loop = $this->templating->renderLoopArray($listTarget, $modelPath, $index, $value, $innerCode);
        }

        return $loop.$this->templating->renderAssign($target, $listTarget);
    }

    /**
     * @param list<string>                $serializerGroups
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForUnion(
        PropertyTypeUnion $subType,
        ?string $apiVersion,
        array $serializerGroups,
        string $target,
        string $modelPath,
        array $stack,
        int $depth = 0,
    ): string {
        $code = '';

        $types = $subType->getTypes();
        $typesWithoutPrimitives = array_filter($types, static fn (PropertyType $subType): bool => !($subType instanceof PropertyTypePrimitive || $subType instanceof PropertyTypeUnknown));

        $hasPrimitives = \count($types) !== \count($typesWithoutPrimitives);
        if ($hasPrimitives) {
            $code .= $this->templating->renderPrimitiveConditional(
                $modelPath,
                $this->templating->renderAssign($target, $modelPath)
            );
        }

        foreach ($typesWithoutPrimitives as $subType) {
            switch ($subType::class) {
                case PropertyTypeClass::class:
                    $code .= $this->templating->renderInstanceOfConditional(
                        $modelPath,
                        $subType->getClassName(),
                        $this->generateCodeForFieldType($subType, $apiVersion, $serializerGroups, $target, $modelPath, $stack, $depth)
                    );
                    break;
                case PropertyTypeIterable::class:
                    $code .= $this->templating->renderArrayConditional(
                        $modelPath,
                        $this->generateCodeForArray($subType, $apiVersion, $serializerGroups, $target, $modelPath, $stack, $depth)
                    );
                    break;
            }
        }

        return $code;
    }

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
