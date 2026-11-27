# Getting started

You need to generate converter files whenever your models change. They follow a
naming scheme that allows the rebuy Serializer to find them. Because the files
have to be pre-generated, you need to specify the exact list of classes,
serializer groups and versions you want to support.

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

See [Versions and groups](versions-and-groups.md), [Handlers](handlers.md), [Events](events.md) and
[Expressions](expressions.md) for the related options.

### Classes

You can register each class for serialization, deserialization, or both:

| Generates                   | One class                    | Several classes                |
|-----------------------------|------------------------------|--------------------------------|
| Serializer and deserializer | `withClass()`                | `withClasses()`                |
| Serializer only             | `withClassForSerializer()`   | `withClassesForSerializer()`   |
| Deserializer only           | `withClassForDeserializer()` | `withClassesForDeserializer()` |

If you configure a class again, the new call replaces the earlier configuration, but only for the side that it
generates. For example, `withClassForSerializer()` after `withClass()` replaces the serializer configuration and keeps
the deserializer.

### Null values

By default, the serializer leaves out fields whose value is `null`. Call `withSerializeNull()` to write them as `null`.

By default, the deserializer skips an incoming `null` and keeps the default value of the property.
Call `withNullAsDefault(false)` to assign the incoming `null` to the property.

### Arrays with an unknown type

If you want to (de)serialize arrays with undefined content, you can do that by
calling `withAllowGenericArrays()` on the builder.

Note: This will only work if the contents of that array are only primitive
types (string, int, float, boolean and nested arrays with only these types).

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

Like JMS Serializer, the rebuy Serializer also provides `fromArray` and
`toArray` for working with array data. As usual when using PHP arrays for JSON
data, you will lose the distinction between empty array and empty object.
