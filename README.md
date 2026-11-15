# Rebuy Serializer - A fast JSON serializer

> [!NOTE]
> This project is based on the original work of [liip/serializer](https://github.com/liip/serializer). This repository is the actively maintained continuation of that project.

# What it supports
This serializer can convert between JSON and PHP objects and back. It uses reflection, Phpdoc and [JMS Serializer](https://github.com/schmittjoh/serializer/) annotations to generate PHP code for the conversion. JMS serializer groups and versions are supported for serializing but not for deserializing.

## Limitations
If you customized JMS Serializer with your own listeners or similar things, this serializer will not work for you. We made an effort to detect when unsupported features are used and raise an error, but recommend that you double check whether the Liip Serializer really produces the exact same as JMS when transforming your data.

`SkipWhenEmpty` skips a property when it is `null`, an empty array or an empty `Countable` (e.g. a Doctrine `Collection`). Unlike JMS, an object that serializes to no properties is still written as an empty object.

# How it works
The Rebuy Serializer generates PHP code based on the PHP models that you specify. It uses the flexible `rebuy/metadata-parser` to gather metadata on the models. A separate file is generated for every version and serializer groups combination to move all logic to the code generation step. This serializer is fast even for massive object trees because the generated PHP code is very simplistic and specific to the usecase, rather than the complex, flexible callback structure that JMS serializer uses. The project we developped this for often has data with up to a megabyte of compact JSON data.

You can use the Rebuy Serializer stand alone. If you are already working with
JMS Serializer, you can also use the drop-in replacement for JMS serializer
[rebuy/serializer-jms-adapter](https://github.com/rebuy-oss/serializer-jms-adapter).
The drop-in adapter implements the JMS interfaces and provides fallback to the
regular JMS serializer for missing generated files and on other errors.

# How to use it
You need to generate converter files whenever your models change. They follow a
naming scheme that allows the Rebuy Serializer to find them. Because the files
have to be pre-generated, you need to specify the exact list of classes,
serializer groups and versions you want to support.

Note: We plan to create a Symfony bundle to integrate the Rebuy Serializer into
Symfony.

## Generate your files
This step needs to be executed during the deployment phase and whenever your
models change.

```php
use Liip\MetadataParser\Builder;
use Liip\MetadataParser\Parser;
use Liip\MetadataParser\RecursionChecker;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\LiipMetadataAttributeParser;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\Serializer\Configuration\GroupCombination;
use Liip\Serializer\Configuration\SerializerConfigBuilder;

$parsers = [
    new ReflectionParser(),
    new PhpDocParser(),
    // For JMS models with Doctrine annotations, install doctrine/annotations and use new JMSParser(new AnnotationReader())
    new JMSParser(),
    new LiipMetadataAttributeParser(),
];
$builder = new Builder(new Parser($parsers), new RecursionChecker(null, []));

$config = SerializerConfigBuilder::create($cacheDirectory)
    ->withMetadataBuilder($builder)
    ->withClasses([Other::class], versions: ['', '1', '2'], groupCombinations: [new GroupCombination(['api'])])
    ->withClass(
        Product::class,
        versions: ['1', '2'],
        groupCombinations: [new GroupCombination(['api', 'detail'], versions: ['2'])],
    )
    ->withClassForSerializer(Summary::class, versions: ['2'])
    ->withClassForDeserializer(Input::class)
    ->withHandlers([new UuidHandler()])
    ->withEventDispatcher($dispatcher)
    ->withExpressionLanguage($expressionLanguage);

$config->buildCompiler()->compile();
$serializer = $config->buildSerializer();
```

`buildCompiler()` throws an exception when you did not call `withMetadataBuilder()`.

### Classes

Use `withClasses()` to name classes one by one. This generates the serializer and the deserializer.
Use `withClassesForSerializer()` or `withClassesForDeserializer()` to generate only one of them.

Use `withClass()` to configure one class. It generates the serializer and the deserializer.
Use `withClassForSerializer()` or `withClassForDeserializer()` to generate only one of them.
`withClassForDeserializer()` and `withClassesForDeserializer()` take no versions and no group combinations.

A later call for the same class replaces the earlier configuration of that class. It only replaces the side that the
call generates: `withClassForSerializer()` after `withClass()` keeps the deserializer.

### Versions and groups

`withClass()`, `withClasses()`, `withClassForSerializer()` and `withClassesForSerializer()` take versions and group
combinations. If you do not give them, the library generates without version and without groups:
`versions: ['']` and `groupCombinations: [new GroupCombination([])]`.

A `GroupCombination` can overwrite the versions for one combination of groups.
The library selects versions and group combinations for each field separately. It never merges lists.

Versions and groups only affect serialization.

#### Version

To generate a file without version, specify version `''` in the list of versions.

#### Groups

To generate a serializer without groups, specify an empty group combination.

Group combinations are `GroupCombination` objects:
`new GroupCombination([])` for no groups, `new GroupCombination(['api'])` for the group `api`.
The builder throws an exception for any other entry.

The versions list and the group combinations list may not be empty.

### Null values

By default, the serializer leaves out fields whose value is `null`. Call `withSerializeNull()` to write them as `null`.

By default, the deserializer skips an incoming `null` and keeps the default value of the property.
Call `withNullAsDefault(false)` to assign the incoming `null` to the property.

### Arrays with an unknown type

If you want to (de)serialize arrays with undefined content, you can do that by
calling `withAllowGenericArrays()` on the builder.

Note: This will only work if the contents of that array are only primitive
types (string, int, float, boolean and nested arrays with only these types).

### Handlers

Handlers let you modify how specific classes are (de-)serialized. This is useful for value objects such as UUIDs or 
any third-party class where you cannot directly add JMS attributes. 

Once registered, a handler is automatically applied to every property of the supported type, regardless of where in the
(de-)serialized model it appears.

#### When to use a handler vs. a virtual property

**Use a virtual property** (`#[Serializer\VirtualProperty]`) when you want to use a different value than the class you 
have at hand, and you have control over that class. Virtual properties are defined on the model itself and only affect 
that model.

**Use a handler** when a custom type needs to be (de-)serialized the same way wherever it appears. In most cases a 
virtual property should be preferred. Handlers should mainly be used if you use third-party classes that have 
to be (de-)serialized in a specific way.

#### Implementing a handler

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

#### Registering handlers

You can register one or multiple handlers with `withHandlers()`:

```php
$config = SerializerConfigBuilder::create($cacheDirectory)
    ->withHandlers([new UuidHandler()])
    // ...
;
```

## Serialize using the generated code
In this example, we serialize an object of class `Product` for version 2:

```php
use Acme\Model\Product;
use Liip\Serializer\Context;
use Liip\Serializer\Serializer;

$serializer = new Serializer($cacheDirectory);

// A model to serialize
$productModel = new Product();

// Your serialized data
$data = $serializer->serialize($productModel, 'json', (new Context())->setVersion(2));
```

## Deserialize using the generated code
```php
use Acme\Model\Product;
use Liip\Serializer\Serializer;

$serializer = new Serializer($cacheDirectory);

// Data to deserialize
$data = '{
    "api_string": "api",
    "detail_string": "details",
    "nested_field": {
        "nested_string": "nested"
    },
    "date": "2018-08-03T00:00:00+02:00",
    "date_immutable": "2016-06-01T00:00:00+02:00"
}';

/** @var Product $model */
$model = $serializer->deserialize($data, Product::class, 'json');
```

## Working with Arrays

Like JMS Serializer, the Rebuy Serializer also provides `fromArray` and
`toArray` for working with array data. As usual when using PHP arrays for JSON
data, you will lose the distinction between empty array and empty object.

## Events

Similar to JMS Serializer, the Rebuy Serializer can dispatch events before and
after (de-)serialization. Pass any implementation of the
`symfony/event-dispatcher-contracts` `EventDispatcherInterface` to
`withEventDispatcher()`. Without a dispatcher, no events are dispatched.

Unlike JMS, the events are only dispatched for the root object, not for nested
objects.

| Event                  | What a listener can change                       |
|------------------------|--------------------------------------------------|
| `PreSerializeEvent`    | Replace the object before it is serialized       |
| `PostSerializeEvent`   | Change the serialized array before JSON encoding |
| `PreDeserializeEvent`  | Change the input array and the target type       |
| `PostDeserializeEvent` | Replace the deserialized object                  |

All events extend `SerializerEvent` and give access to the `Context` and the
format. The format is `null` for `toArray` and `fromArray`.

In this example, we add a field to every serialized `Product`:

```php
use Acme\Model\Product;
use Liip\Serializer\Configuration\SerializerConfigBuilder;
use Liip\Serializer\Event\PostSerializeEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();
$dispatcher->addListener(PostSerializeEvent::class, static function (PostSerializeEvent $event): void {
    if (!$event->getObject() instanceof Product) {
        return;
    }

    $event->setData([...$event->getData(), 'type' => 'product']);
});

$serializer = SerializerConfigBuilder::create($cacheDirectory)
    ->withEventDispatcher($dispatcher)
    ->buildSerializer();
```

## Expressions

> [!IMPORTANT]
> Expressions make (de-)serialization slower, because every expression is evaluated at runtime. Give the
> `ExpressionLanguage` a cache that does not serialize its values, like `new ArrayAdapter(deepClone: false)`
> (`storeSerialized` in older Symfony versions). The default cache is about 5 times slower.

Like JMS Serializer, the rebuy Serializer supports expressions in the JMS attributes `#[Exclude(if: ...)]` and
`#[Expose(if: ...)]` on properties and in `#[Exclude(if: ...)]` on classes. To use them, install the expression
language with `composer require symfony/expression-language`.

The expressions are only added to the generated code when you call `withExpressionLanguage()`. The same call
makes the `Serializer` evaluate the expressions at runtime.

The expressions get these variables:

| Variable  | Value                                                                     |
|-----------|---------------------------------------------------------------------------|
| `object`  | The object that holds the property, or the object itself on a class level |
| `context` | The `Context` passed to the `Serializer`, or `null`                       |

Like in JMS, `object` is `null` when deserializing.

Unlike JMS, `property_metadata` and `class_metadata` are not available.

An excluded object is handled like `null`: the field is left out, or written as `null` when `withSerializeNull()` is
set. An excluded root object is serialized as an empty object and deserializes to `null`.

In this example, we enable expressions for the generated files and for the `Serializer`:

```php
use Liip\Serializer\Configuration\SerializerConfigBuilder;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

$config = SerializerConfigBuilder::create($cacheDirectory)
    ->withMetadataBuilder($builder)
    ->withExpressionLanguage(new ExpressionLanguage(new ArrayAdapter(deepClone: false)))
    // ...
;

$config->buildCompiler()->compile();
$serializer = $config->buildSerializer();
```

# Where do I go for help?
If you need help, please open an issue on github.

# Background: Why an Object Serializer Generator?
We started having performance problem with large object structures (often
several Megabyte of JSON data). Code analysis showed that a lot of time is
spent calling the JMS callback structure hundred thousands of times.
Simplistic generated PHP code is much more efficient at runtime.

# Implementation Notes
The `DeserializerGenerator` and `SerializerGenerator` produce PHP code from the
metadata. The generators use twig to render the PHP code, for better
readability. See the `Template` namespace.

The indentation in the generated code is not respecting levels of nesting. We
could carry around the depth and prepend whitespace, but apart from debugging,
nobody will look at the generated code.

We decided to not use reflection, for better performance. Properties need to be
public or have a public getter for serialization. For deserialization, we also
match constructor arguments by name, so as long as a non-public property name
matches a constructor argument, it needs no setter.
