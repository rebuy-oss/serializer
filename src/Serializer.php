<?php

declare(strict_types=1);

namespace Liip\Serializer;

use Liip\Serializer\Event\PostDeserializeEvent;
use Liip\Serializer\Event\PostSerializeEvent;
use Liip\Serializer\Event\PreDeserializeEvent;
use Liip\Serializer\Event\PreSerializeEvent;
use Liip\Serializer\Exception\Exception;
use Liip\Serializer\Exception\UnsupportedFormatException;
use Liip\Serializer\Exception\UnsupportedTypeException;
use Pnz\JsonException\Json;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A serializer that loads the pre-generated PHP function for the requested version and groups.
 *
 * Almost all decisions have been taken during generating the PHP code, rather than at runtime.
 *
 * The code generation is - at least for now - only implemented for JSON.
 */
final class Serializer implements SerializerInterface
{
    /**
     * @var array<string, \Closure>
     */
    private array $serializerFunctions = [];

    /**
     * @var array<string, \Closure>
     */
    private array $deserializerFunctions = [];

    public function __construct(
        private readonly string $cacheDirectory,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Serializing primitive types is not currently implemented and will lead
     * to an UnsupportedTypeException.
     */
    public function serialize(mixed $data, string $format, ?Context $context = null): string
    {
        if ('json' !== $format) {
            throw new UnsupportedFormatException('Liip serializer only supports JSON for now');
        }

        try {
            return Json::encode($this->objectToArray($data, true, $context, $format), \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new Exception(\sprintf('Failed to JSON encode data for %s. This is not supposed to happen.', get_debug_type($data)), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Version or groups are currently not implemented for deserialization and
     * passing a context with one of those values set will lead to an Exception.
     */
    public function deserialize(string $data, string $type, string $format, ?Context $context = null): mixed
    {
        if ('json' !== $format) {
            throw new UnsupportedFormatException('Liip serializer only supports JSON for now');
        }

        try {
            $array = Json::decode($data, true);
        } catch (\JsonException $e) {
            throw new Exception('Failed to JSON decode data. This is not supposed to happen.', 0, $e);
        }

        return $this->arrayToObject($array, $type, $context, $format);
    }

    /**
     * {@inheritdoc}
     *
     * Serializing primitive types is not currently implemented and will lead
     * to an UnsupportedTypeException.
     */
    public function toArray($data, ?Context $context = null): array
    {
        return $this->objectToArray($data, false, $context, null);
    }

    /**
     * {@inheritdoc}
     *
     * Version or groups are currently not implemented for deserialization and
     * passing a context with one of those values set will lead to an Exception.
     */
    public function fromArray(array $data, string $type, ?Context $context = null): mixed
    {
        return $this->arrayToObject($data, $type, $context, null);
    }

    /**
     * @param mixed[] $data
     */
    private function arrayToObject(array $data, string $type, ?Context $context, ?string $format): mixed
    {
        if ($context instanceof Context && ($context->getVersion() || \count($context->getGroups()))) {
            throw new Exception('Version and group support is not implemented for deserialization. It is only supported for serialization');
        }

        if (null !== $this->eventDispatcher) {
            $event = $this->eventDispatcher->dispatch(new PreDeserializeEvent($data, $type, $context, $format));
            $data = $event->getData();
            $type = $event->getType();
        }

        $function = $this->deserializerFunctions[$type] ?? null;

        if (null === $function) {
            $functionName = DeserializerGenerator::buildDeserializerFunctionName($type);
            $filename = \sprintf('%s/%s.php', $this->cacheDirectory, $functionName);
            if (!file_exists($filename)) {
                throw UnsupportedTypeException::typeUnsupportedDeserialization($type);
            }

            require_once $filename;

            if (!\function_exists($functionName)) {
                throw new Exception(\sprintf('Internal Error: Deserializer for %s in file %s does not have expected function %s', $type, $filename, $functionName));
            }

            $function = $functionName(...);
            $this->deserializerFunctions[$type] = $function;
        }

        try {
            $object = $function($data, $this->expressionLanguage, $context);
        } catch (\Throwable $t) {
            throw new Exception('Error during deserialization', 0, $t);
        }

        if (null === $this->eventDispatcher) {
            return $object;
        }

        $event = $this->eventDispatcher->dispatch(new PostDeserializeEvent($object, $data, $context, $format));

        return $event->getObject();
    }

    /**
     * @return mixed[]
     */
    private function objectToArray(mixed $data, bool $useStdClass, ?Context $context, ?string $format): array
    {
        if (!\is_object($data)) {
            throw new UnsupportedTypeException('The Liip Serializer only works for objects');
        }

        if (null !== $this->eventDispatcher) {
            $event = $this->eventDispatcher->dispatch(new PreSerializeEvent($data, $context, $format));
            $data = $event->getObject();
        }

        $type = $data::class;
        $groups = [];
        $version = null;
        if ($context) {
            $groups = $context->getGroups();
            if ($context->getVersion()) {
                $version = $context->getVersion();
            }
        }

        $cacheKey = $this->buildSerializerCacheKey($type, $version, $groups);
        $function = $this->serializerFunctions[$cacheKey] ?? null;

        if (null === $function) {
            $functionName = SerializerGenerator::buildSerializerFunctionName($type, $version, $groups);
            $filename = \sprintf('%s/%s.php', $this->cacheDirectory, $functionName);
            if (!file_exists($filename)) {
                throw UnsupportedTypeException::typeUnsupportedSerialization($type, $version, $groups);
            }

            require_once $filename;

            if (!\function_exists($functionName)) {
                throw new Exception(\sprintf('Internal Error: Serializer for %s in file %s does not have expected function %s', $type, $filename, $functionName));
            }

            $function = $functionName(...);
            $this->serializerFunctions[$cacheKey] = $function;
        }

        try {
            $array = $function($data, $useStdClass, $this->expressionLanguage, $context);
        } catch (\Throwable $t) {
            throw new Exception('Error during serialization', 0, $t);
        }

        if (null === $this->eventDispatcher) {
            return $array;
        }

        $event = $this->eventDispatcher->dispatch(new PostSerializeEvent($array, $data, $context, $format));

        return $event->getData();
    }

    /**
     * @param list<string> $groups
     */
    private function buildSerializerCacheKey(string $type, ?string $version, array $groups): string
    {
        if (null === $version && 0 === \count($groups)) {
            return $type;
        }

        return $type.'|'.$version.'|'.implode(',', $groups);
    }
}
