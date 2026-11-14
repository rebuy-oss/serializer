<?php

declare(strict_types=1);

namespace Liip\Serializer\Configuration;

use Liip\Serializer\DeserializerHandlerInterface;
use Liip\Serializer\SerializerHandlerInterface;

final readonly class GeneratorConfiguration
{
    /**
     * @param list<ClassToGenerate>                                         $serializerClasses
     * @param list<class-string>                                            $deserializerClasses
     * @param bool                                                          $allowGenericArrays  If this is false, arrays with sub type PropertyTypeUnknown are treated as error.
     *                                                                                           If this is true, deserialize assigns the raw array and serialize just takes the raw content of the field.
     * @param list<SerializerHandlerInterface|DeserializerHandlerInterface> $handlers
     * @param bool                                                          $serializeNull       If this is false, do not wrap conditionals around assigning fields whose value is null
     * @param bool                                                          $nullAsDefault       If this is false, do not wrap conditionals around assigning fields whose value is null
     */
    public function __construct(
        private array $serializerClasses = [],
        private array $deserializerClasses = [],
        private bool $allowGenericArrays = false,
        private bool $expressionLanguage = false,
        private array $handlers = [],
        private bool $serializeNull = false,
        private bool $nullAsDefault = true,
    ) {
    }

    /**
     * @return list<ClassToGenerate>
     */
    public function getSerializerClasses(): array
    {
        return $this->serializerClasses;
    }

    /**
     * @return list<class-string>
     */
    public function getDeserializerClasses(): array
    {
        return $this->deserializerClasses;
    }

    public function shouldAllowGenericArrays(): bool
    {
        return $this->allowGenericArrays;
    }

    public function shouldEvaluateExpressions(): bool
    {
        return $this->expressionLanguage;
    }

    /**
     * @param class-string $className
     */
    public function findSerializerHandlerForClass(string $className): ?SerializerHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof SerializerHandlerInterface && $this->supportsClass($handler->getSerializationClasses(), $className)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param class-string $className
     */
    public function findDeserializerHandlerForClass(string $className): ?DeserializerHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof DeserializerHandlerInterface && $this->supportsClass($handler->getDeserializationClasses(), $className)) {
                return $handler;
            }
        }

        return null;
    }

    public function shouldSerializeNull(): bool
    {
        return $this->serializeNull;
    }

    public function shouldTreatNullAsDefault(): bool
    {
        return $this->nullAsDefault;
    }

    /**
     * @param class-string[] $supportedClasses
     * @param class-string   $actualClass
     */
    private function supportsClass(array $supportedClasses, string $actualClass): bool
    {
        return array_any($supportedClasses, static fn (string $supportedClass): bool => is_a($actualClass, $supportedClass, true));
    }
}
