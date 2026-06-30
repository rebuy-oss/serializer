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
use Liip\MetadataParser\Reducer\TakeBestReducer;
use Liip\Serializer\Configuration\ClassToGenerate;
use Liip\Serializer\Configuration\GeneratorConfiguration;
use Liip\Serializer\Path\ArrayEntry;
use Liip\Serializer\Path\ArrayPath;
use Liip\Serializer\Path\ModelPath;
use Liip\Serializer\Template\Deserialization;
use Symfony\Component\Filesystem\Filesystem;

final class DeserializerGenerator
{
    private const FILENAME_PREFIX = 'deserialize';

    private Filesystem $filesystem;

    private GeneratorConfiguration $configuration;

    /**
     * @param list<class-string> $classesToGenerate This is a list of FQCN classnames
     */
    public function __construct(
        private Deserialization $templating,
        array $classesToGenerate,
        private string $cacheDirectory,
        ?GeneratorConfiguration $configuration = null,
    ) {
        $this->filesystem = new Filesystem();
        $this->configuration = $this->createGeneratorConfiguration($configuration, $classesToGenerate);
    }

    public static function buildDeserializerFunctionName(string $className): string
    {
        return self::FILENAME_PREFIX.'_'.str_replace('\\', '_', $className);
    }

    public function generate(Builder $metadataBuilder): void
    {
        $this->filesystem->mkdir($this->cacheDirectory);

        /** @var ClassToGenerate $classToGenerate */
        foreach ($this->configuration as $classToGenerate) {
            // we do not use the oldest version reducer here and hope for the best
            // otherwise we end up with generated property names for accessor methods
            $classMetadata = $metadataBuilder->build($classToGenerate->getClassName(), [
                new TakeBestReducer(),
            ]);
            $this->writeFile($classMetadata);
        }
    }

    private function writeFile(ClassMetadata $classMetadata): void
    {
        if (\count($classMetadata->getConstructorParameters())) {
            throw new \Exception(\sprintf('We currently do not support deserializing when the root class has a non-empty constructor. Class %s', $classMetadata->getClassName()));
        }

        $functionName = self::buildDeserializerFunctionName($classMetadata->getClassName());
        $arrayPath = new ArrayPath('jsonData');

        $code = $this->templating->renderFunction(
            $functionName,
            $classMetadata->getClassName(),
            (string) $arrayPath,
            $this->generateCodeForClass($classMetadata, $arrayPath, new ModelPath('model'))
        );

        $this->filesystem->dumpFile(\sprintf('%s/%s.php', $this->cacheDirectory, $functionName), $code);
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForClass(
        ClassMetadata $classMetadata,
        ArrayPath $arrayPath,
        ModelPath $modelPath,
        array $stack = [],
    ): string {
        $className = $classMetadata->getClassName();
        $handler = $this->configuration->findDeserializerHandlerForClass($className);
        if (null !== $handler) {
            return $this->templating->renderAssignJsonDataToField((string) $modelPath, $handler->generateDeserializeExpression($className, (string) $arrayPath));
        }

        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();
        if (null !== $discriminatorMetadata && $discriminatorMetadata->baseClass == $className) {
            return $this->generateCodeForDiscriminatorClass($classMetadata, $arrayPath, $modelPath, $stack);
        }

        $stack[$className] = ($stack[$className] ?? 0) + 1;

        $constructorArgumentNames = [];
        $overwrittenNames = [];
        $initCode = '';
        $code = '';
        foreach ($classMetadata->getProperties() as $propertyMetadata) {
            $propertyType = $propertyMetadata->getType();
            $propertyArrayPath = $arrayPath->withFieldName($propertyMetadata->getSerializedName());

            if ($classMetadata->hasConstructorParameter($propertyMetadata->getName())) {
                $argument = $classMetadata->getConstructorParameter($propertyMetadata->getName());
                $default = var_export($argument->isRequired() ? null : $argument->getDefaultValue(), true);
                $tempVariable = ModelPath::tempVariable([(string) $modelPath, $propertyMetadata->getName()]);
                if (\array_key_exists($propertyMetadata->getName(), $constructorArgumentNames)) {
                    $overwrittenNames[$propertyMetadata->getName()] = true;
                }
                $constructorArgumentNames[$propertyMetadata->getName()] = (string) $tempVariable;
                $getValue = $this->generateInnerCodeForFieldType($propertyType, $propertyArrayPath, $tempVariable, $stack);

                if ($this->configuration->shouldTreatNullAsDefault()) {
                    $initCode .= $this->templating->renderArgument(
                        (string) $tempVariable,
                        $default,
                        $this->templating->renderConditional((string) $propertyArrayPath, $getValue)
                    );
                } else {
                    $initCode .= $this->templating->renderDynamicKeyExistsConditional(
                        (string) $arrayPath,
                        var_export($propertyMetadata->getSerializedName(), true),
                        $getValue,
                        $this->templating->renderArgument((string) $tempVariable, $default, null),
                    );
                }
            } else {
                $code .= $this->generateCodeForProperty($propertyMetadata, $propertyArrayPath, $modelPath, $stack);
            }
        }

        foreach ($classMetadata->getPostDeserializeMethods() as $method) {
            $code .= $this->templating->renderPostMethod((string) $modelPath, $method);
        }

        $constructorArguments = [];
        foreach ($classMetadata->getConstructorParameters() as $definition) {
            if (\array_key_exists($definition->getName(), $constructorArgumentNames)) {
                $constructorArguments[] = $constructorArgumentNames[$definition->getName()];
                continue;
            }
            if ($definition->isRequired()) {
                $msg = \sprintf('Unknown constructor argument "%s". Class %s only has properties that tell how to handle %s.', $definition->getName(), $className, implode(', ', array_keys($constructorArgumentNames)));
                if ($overwrittenNames) {
                    $msg .= \sprintf(' Multiple definitions for fields %s seen - the last one overwrites previous ones.', implode(', ', array_keys($overwrittenNames)));
                }
                throw new \Exception($msg);
            }
            $constructorArguments[] = var_export($definition->getDefaultValue(), true);
        }
        if (\count($constructorArgumentNames) > 0) {
            $code .= $this->templating->renderUnset(array_values($constructorArgumentNames));
        }

        return $this->templating->renderClass((string) $modelPath, $className, $constructorArguments, $code, $initCode);
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForDiscriminatorClass(
        ClassMetadata $classMetadata,
        ArrayPath $arrayPath,
        ModelPath $modelPath,
        array $stack = [],
    ): string {
        $code = '';
        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();
        $discriminatorFieldPath = $arrayPath->withFieldName($discriminatorMetadata->propertyName);
        foreach ($discriminatorMetadata->classMap as $typeValue => $class) {
            $code .= $this->templating->renderDiscriminatorConditional(
                (string) $discriminatorFieldPath,
                $typeValue,
                $this->generateCodeForClass($discriminatorMetadata->getMetadataForClass($class), $arrayPath, $modelPath, $stack)
            );
        }

        return $code;
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForProperty(
        PropertyMetadata $propertyMetadata,
        ArrayPath $arrayPath,
        ModelPath $modelPath,
        array $stack,
    ): string {
        if ($propertyMetadata->isReadOnly()) {
            return '';
        }

        if (Recursion::hasMaxDepthReached($propertyMetadata, $stack)) {
            return '';
        }

        /** @var non-empty-list<ArrayEntry> $arrayPathLastKey */
        [$arrayPathBase, $arrayPathLastKey] = $arrayPath->splitBack();
        $arrayPathLastKey = array_pop($arrayPathLastKey)->getPath();
        $propertyType = $propertyMetadata->getType();

        if ($propertyMetadata->getAccessor()->hasSetterMethod()) {
            $tempVariable = ModelPath::tempVariable([(string) $modelPath, $propertyMetadata->getName()]);
            $getValue = $this->generateInnerCodeForFieldType($propertyType, $arrayPath, $tempVariable, $stack);
            $setIntoModel = $this->templating->renderSetter((string) $modelPath, $propertyMetadata->getAccessor()->getSetterMethod(), (string) $tempVariable);

            if ($this->configuration->shouldTreatNullAsDefault()) {
                $code = $this->templating->renderConditional((string) $arrayPath, $getValue);
                $code .= $this->templating->renderConditional((string) $tempVariable, $setIntoModel);
            } else {
                $code = $this->templating->renderDynamicKeyExistsConditional((string) $arrayPathBase, (string) $arrayPathLastKey, "{$getValue}    {$setIntoModel}");
            }

            return $code;
        }

        $modelPropertyPath = $modelPath->withPath($propertyMetadata->getName());
        $getValue = $this->generateInnerCodeForFieldType($propertyType, $arrayPath, $modelPropertyPath, $stack);

        if ($this->configuration->shouldTreatNullAsDefault()) {
            return $this->templating->renderConditional((string) $arrayPath, $getValue);
        }

        if (!$propertyType->isNullable() || $this->typeDeserializesAsExpression($propertyType)) {
            // No need to check for null values if not-nullable or the null-check is handled within a conversion expression
            return $this->templating->renderDynamicKeyExistsConditional((string) $arrayPathBase, (string) $arrayPathLastKey, $getValue);
        }

        $dataVar = $arrayPath;
        $makeIntermediate = '';

        if ($propertyType instanceof PropertyTypeClass) {
            $dataVar = ArrayPath::inventVariable((string) $arrayPath, 'class');
            $makeIntermediate .= $this->templating->renderAssignJsonDataToField((string) $dataVar, (string) $arrayPath)."\n";
        }

        $deserializeValue = $makeIntermediate.$this->generateInnerCodeForFieldType($propertyType->asNullable(false), $dataVar, $modelPropertyPath, $stack);
        $setNull = $this->templating->renderArgument((string) $modelPropertyPath, 'null', null);

        return $this->templating->renderDynamicKeyExistsConditional(
            (string) $arrayPathBase,
            (string) $arrayPathLastKey,
            $this->templating->renderIsNotNullConditional((string) $arrayPath, $deserializeValue, $setNull)
        );
    }

    /**
     * Whether a PropertyType requires the target to be set to null in a separate statement (returns true), or its expression already allows the null case (returns false)
     *
     * @return bool True if the type needs a separate statement for the null case
     */
    private function typeDeserializesAsExpression(PropertyType $type): bool
    {
        switch ($type) {
            case $type instanceof PropertyTypePrimitive:
            case $type instanceof PropertyTypeUnknown:
            case $type instanceof PropertyTypeEnum:
                return true;
            case $type instanceof PropertyTypeDateTime:
                $formats = $type->getDeserializeFormats() ?: $type->getFormat();
                if (!$formats) {
                    return true;
                }
                if ((\is_string($formats) || (1 === \count($formats))) && !$type->isNullable()) {
                    return true;
                }

                return false;

            case $type instanceof PropertyTypeIterable:
                $subType = $type->getLeafType();

                return !$type->isTraversable() && ($subType instanceof PropertyTypePrimitive || $subType instanceof PropertyTypeUnknown);

            case $type instanceof PropertyTypeClass:
            case $type instanceof PropertyTypeUnion:
                return false;
        }

        return false;
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateInnerCodeForFieldType(
        PropertyType $type,
        ArrayPath $arrayPath,
        ModelPath $modelPropertyPath,
        array $stack,
    ): string {
        switch ($type) {
            case $type instanceof PropertyTypeIterable:
                if ($type->isTraversable()) {
                    return $this->generateCodeForArrayCollection($type, $arrayPath, $modelPropertyPath, $stack);
                }

                return $this->generateCodeForArray($type, $arrayPath, $modelPropertyPath, $stack);

            case $type instanceof PropertyTypeDateTime:
                $formats = $type->getDeserializeFormats() ?: (null !== $type->getFormat() ? [$type->getFormat()] : null);
                if (null !== $formats) {
                    return $this->templating->renderAssignDateTimeFromFormat($type->isImmutable(), (string) $modelPropertyPath, (string) $arrayPath, $formats, $type->getZone(), nullCheck: $type->isNullable());
                }

                return $this->templating->renderAssignDateTimeToField($type->isImmutable(), (string) $modelPropertyPath, (string) $arrayPath, nullCheck: $type->isNullable());

            case $type instanceof PropertyTypePrimitive && 'float' === $type->getTypeName():
                return $this->templating->renderAssignJsonDataToFieldWithCasting((string) $modelPropertyPath, (string) $arrayPath, 'float', $type->isNullable());

            case $type instanceof PropertyTypePrimitive:
            case $type instanceof PropertyTypeUnknown:
                return $this->templating->renderAssignJsonDataToField((string) $modelPropertyPath, (string) $arrayPath);

            case $type instanceof PropertyTypeEnum:
                // It handles null checks itself
                return $this->generateCodeForEnumField($type, $modelPropertyPath, $arrayPath);

            case $type instanceof PropertyTypeClass:
                return $this->generateCodeForClass($type->getClassMetadata(), $arrayPath, $modelPropertyPath, $stack);

            case $type instanceof PropertyTypeUnion:
                return $this->generateCodeForUnion($type, $arrayPath, $modelPropertyPath, $stack);

            default:
                throw new \Exception('Unexpected type '.$type::class.' at '.$modelPropertyPath);
        }
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForUnion(
        PropertyTypeUnion $type,
        ArrayPath $arrayPath,
        ModelPath $modelPath,
        array $stack,
    ): string {
        $code = '';

        $types = $type->getTypes();
        $typesWithoutPrimitives = array_filter($types, static function (PropertyType $subType): bool {
            return !($subType instanceof PropertyTypePrimitive || $subType instanceof PropertyTypeIterable);
        });

        $fieldName = $type->getFieldName();
        if (null !== $fieldName) {
            $discriminatorFieldPath = $arrayPath->withFieldName($fieldName);

            foreach ($type->getTypeMap() as $typeValue => $class) {
                $classType = $type->getTypeByClassName($class);
                $code .= $this->templating->renderDiscriminatorConditional(
                    (string) $discriminatorFieldPath,
                    $typeValue,
                    $this->generateCodeForClass($classType->getClassMetadata(), $arrayPath, $modelPath, $stack)
                );
            }

            return $code;
        }

        if (0 !== \count($typesWithoutPrimitives)) {
            throw new \Exception('Found union type that contains primitives and non primitives, which is currently not supported.');
        }

        $amountOfTypes = \count($types);
        foreach ($types as $key => $subType) {
            $phpType = 'array';
            if ($subType instanceof PropertyTypePrimitive) {
                $phpType = $subType->getTypeName();
            }

            $withElseBlock = $key !== ($amountOfTypes - 1);
            $code .= $this->templating->renderPrimitiveConditional(
                $phpType,
                (string) $arrayPath,
                $this->templating->renderAssignJsonDataToFieldWithCast($phpType, (string) $modelPath, (string) $arrayPath),
                $withElseBlock
            );
        }

        return $code;
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForArray(
        PropertyTypeIterable $type,
        ArrayPath $arrayPath,
        ModelPath $modelPath,
        array $stack,
    ): string {
        if ($type->getSubType() instanceof PropertyTypePrimitive) {
            // for arrays of scalars, copy the field even when its an empty array
            return $this->templating->renderAssignJsonDataToField((string) $modelPath, (string) $arrayPath);
        }

        $index = ArrayPath::indexVariable((string) $arrayPath);
        $value = ArrayPath::inventVariable((string) $arrayPath, 'value');
        $modelPropertyPath = $modelPath->withArray((string) $index);
        $subType = $type->getSubType();

        switch ($subType) {
            case $subType instanceof PropertyTypeIterable:
                $innerCode = $this->generateCodeForArray($subType, $value, $modelPropertyPath, $stack);
                break;

            case $subType instanceof PropertyTypeEnum:
                $innerCode = $this->generateCodeForEnumField($subType, $modelPropertyPath, $value);
                break;

            case $subType instanceof PropertyTypeClass:
                $innerCode = $this->generateCodeForClass($subType->getClassMetadata(), $value, $modelPropertyPath, $stack);
                break;

            case $subType instanceof PropertyTypeUnknown && $this->configuration->shouldAllowGenericArrays():
                return $this->templating->renderAssignJsonDataToField((string) $modelPath, (string) $arrayPath);

            default:
                throw new \Exception('Unexpected array subtype '.$subType::class);
        }

        if ('' === $innerCode) {
            return '';
        }

        $code = $this->templating->renderInitArray((string) $modelPath);
        $code .= $this->templating->renderLoop((string) $arrayPath, (string) $index, (string) $value, $innerCode);

        return $code;
    }

    /**
     * @param array<string, positive-int> $stack
     */
    private function generateCodeForArrayCollection(
        PropertyTypeIterable $type,
        ArrayPath $arrayPath,
        ModelPath $modelPath,
        array $stack,
    ): string {
        $tmpVariable = ModelPath::tempVariable([(string) $modelPath]);
        $innerCode = $this->generateCodeForArray($type, $arrayPath, $tmpVariable, $stack);

        if ('' === $innerCode) {
            return '';
        }

        return $innerCode.$this->templating->renderArrayCollection((string) $modelPath, (string) $tmpVariable);
    }

    private function generateCodeForEnumField(
        PropertyTypeEnum $type,
        ModelPath $modelPath,
        ArrayPath $arrayPath,
    ): string {
        /** @var class-string<\BackedEnum> $enumClass */
        $enumClass = $type->getClassName();

        if ($type->shouldSerializeAsValue()) {
            return $this->templating->renderAssignBackedEnum($enumClass, (string) $modelPath, (string) $arrayPath, $type->isNullable());
        }

        return $this->templating->renderAssignUnitEnum($enumClass, (string) $modelPath, (string) $arrayPath, $type->isNullable());
    }

    /**
     * @param list<class-string> $classesToGenerate
     */
    private function createGeneratorConfiguration(
        ?GeneratorConfiguration $configuration,
        array $classesToGenerate,
    ): GeneratorConfiguration {
        if (null === $configuration) {
            $configuration = new GeneratorConfiguration([], []);
        }

        foreach ($classesToGenerate as $className) {
            $configuration->addClassToGenerate(new ClassToGenerate($configuration, $className));
        }

        return $configuration;
    }
}
