# Handlers

Handlers let you modify how specific classes are (de-)serialized. This is useful for value objects such as UUIDs or 
any third-party class where you cannot directly add JMS attributes. 

Once registered, a handler is automatically applied to every property of the supported type, regardless of where in the
(de-)serialized model it appears.

## When to use a handler vs. a virtual property

**Use a virtual property** (`#[Serializer\VirtualProperty]`) when you want to use a different value than the class you 
have at hand, and you have control over that class. Virtual properties are defined on the model itself and only affect 
that model.

**Use a handler** when a custom type needs to be (de-)serialized the same way wherever it appears. In most cases a 
virtual property should be preferred. Handlers should mainly be used if you use third-party classes that have 
to be (de-)serialized in a specific way.

## Implementing a handler

Implement `SerializerHandlerInterface` for serialization and/or `DeserializerHandlerInterface` for deserialization. 
Both return **PHP code expressions** (strings) that are embedded directly into the generated code — not values.

A common example where such a handler makes sense is, when you want to (de-)serialize UUID objects. The serialized value
is actually a string (e.g. `dfbd707c-3945-4a90-a6e7-e4e58846ebe7`) while the deserialized value is again the UUID object
from your preferred library.

In the following example we use the UUID implementation from the [symfony/uid package](https://packagist.org/packages/symfony/uid).

```php
use Liip\Serializer\DeserializerHandlerInterface;
use Liip\Serializer\SerializerHandlerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Uid\UuidV8;

/**
 * We write a single handler for both serialization and deserialization.
 */
class UuidHandler implements SerializerHandlerInterface, DeserializerHandlerInterface
{
    private const SUPPORTED_CLASSES = [
        Uuid::class,
        UuidV1::class,
        UuidV3::class,
        UuidV4::class,
        UuidV5::class,
        UuidV6::class,
        UuidV7::class,
        UuidV8::class,
    ];

    public function generateSerializeExpression(string $className, string $modelPath): string
    {
        // $modelPath is a PHP expression for the property value, e.g. "$model->id"
        return $modelPath.'->toString()';
    }

    public function generateDeserializeExpression(string $className, string $arrayPath): string
    {
        // $arrayPath is a PHP expression for the raw array value, e.g. "$array['id']"
        return '\\'.$className.'::fromString('.$arrayPath.')';
    }

    public function getSerializationClasses(string $className): array
    {
        return self::SUPPORTED_CLASSES;
    }

    public function getDeserializationClasses(string $className): array
    {
        return self::SUPPORTED_CLASSES;
    }
}
```

## Registering handlers

You can register one or multiple handlers with `withHandlers()`:

```php
$config = SerializerConfigBuilder::create($cacheDirectory)
    ->withHandlers([new UuidHandler()])
    // ...
;
```
