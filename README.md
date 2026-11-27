# rebuy Serializer - A fast JSON serializer

> [!NOTE]
> This project is based on the original work of [liip/serializer](https://github.com/liip/serializer). This repository is the actively maintained continuation of that project.

A JSON (de-)serializer for PHP that generates its code ahead of time.

- **Generated, not interpreted:** one plain PHP file per model, version and group combination
- **JMS compatible:** reuses your existing [JMS Serializer](https://github.com/schmittjoh/serializer/) attributes, with a [drop-in adapter](#drop-in-replacement-for-jms-serializer) if you need it
- **Fast at scale:** built for object trees with megabytes of JSON

## Contents

**This page**

- [Installation](#installation)
- [Quickstart](#quickstart)
- [Supported JMS features](#supported-jms-features)
- [Drop-in replacement for JMS Serializer](#drop-in-replacement-for-jms-serializer)
- [Help](#help)

**Docs**

- [Getting started](docs/getting-started.md) - generate the files, configure classes, serialize and deserialize
- [Versions and groups](docs/versions-and-groups.md) - generate serializers for JMS versions and groups
- [Handlers](docs/handlers.md) - customize how specific classes are (de-)serialized
- [Events](docs/events.md) - change data before and after (de-)serialization
- [Expressions](docs/expressions.md) - use `Exclude(if: ...)` and `Expose(if: ...)`
- [Limitations](docs/limitations.md) - differences to JMS Serializer
- [Internals](docs/internals.md) - how it works and why

## Installation

```bash
composer require rebuy/serializer
```

## Quickstart

Generate the files during deployment and whenever your models change:

```php
use Liip\MetadataParser\Builder;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\MetadataParser\Parser;
use Liip\MetadataParser\RecursionChecker;
use Liip\Serializer\Configuration\SerializerConfigBuilder;

$builder = new Builder(
    new Parser([new ReflectionParser(), new PhpDocParser(), new JMSParser()]),
    new RecursionChecker(null, []),
);

$config = SerializerConfigBuilder::create($cacheDirectory)
    ->withMetadataBuilder($builder)
    ->withClass(Product::class);

$config->buildCompiler()->compile();
```

Then serialize and deserialize with the generated code:

```php
$serializer = $config->buildSerializer();

$json = $serializer->serialize($product, 'json');
$product = $serializer->deserialize($json, Product::class, 'json');
```

See [Getting started](docs/getting-started.md) for all options.

## Supported JMS features

The generation fails when a class or property uses a JMS attribute that is not supported. Unsupported attributes on
methods are ignored. Custom JMS handlers, listeners and similar extensions do not work. See [Limitations](docs/limitations.md).

✅ supported, ⚠️ supported with differences, ❌ not supported, – not applicable

### Attributes

| Feature                         | Serialize | Deserialize | Notes                                                              |
|---------------------------------|-----------|-------------|--------------------------------------------------------------------|
| `Type`                          | ✅         | ✅           |                                                                    |
| `SerializedName`                | ✅         | ✅           |                                                                    |
| `Exclude`                       | ✅         | ✅           | Not on classes without `if`                                        |
| `Expose`, `ExclusionPolicy`     | ✅         | ✅           |                                                                    |
| `Exclude(if)`, `Expose(if)`     | ✅         | ✅           | See [Expressions](docs/expressions.md)                             |
| `Accessor`                      | ✅         | ✅           |                                                                    |
| `AccessorOrder`                 | ✅         | –           |                                                                    |
| `VirtualProperty`               | ✅         | –           |                                                                    |
| `ReadOnlyProperty`              | ✅         | –           |                                                                    |
| `Groups`, `Since`, `Until`      | ✅         | ❌           | See [Versions and groups](docs/versions-and-groups.md)             |
| `SkipWhenEmpty`                 | ⚠️        | –           | See [Limitations](docs/limitations.md#skipwhenempty)               |
| `Inline`                        | ✅         | ✅           | Not for scalar types                                               |
| `MaxDepth`                      | ✅         | ✅           |                                                                    |
| `Discriminator`                 | ✅         | ✅           |                                                                    |
| `UnionDiscriminator`            | ✅         | ✅           | Required to deserialize unions of non-primitive types              |
| `PostDeserialize`               | –         | ✅           |                                                                    |
| `PreSerialize`, `PostSerialize` | ❌         | –           | Ignored. Use [Events](docs/events.md)                              |
| `HandlerCallback`               | ❌         | ❌           | Ignored. Use [Handlers](docs/handlers.md)                          |
| `AccessType`                    | ❌         | ❌           | Generation fails                                                   |
| Any other JMS attribute         | ❌         | ❌           | Generation fails on classes and properties, ignored on methods     |

### Runtime features

| Feature                     | Serialize | Deserialize | Notes                                            |
|-----------------------------|-----------|-------------|--------------------------------------------------|
| Custom handlers             | ❌         | ❌           | Use [Handlers](docs/handlers.md)                 |
| Event subscribers           | ❌         | ❌           | Use [Events](docs/events.md)                     |
| Custom exclusion strategies | ❌         | ❌           |                                                  |
| Context attributes          | ❌         | ❌           | The `Context` only holds groups and the version  |
| `serializeNull`             | ⚠️        | –           | Set once with `withSerializeNull()`              |

## Drop-in replacement for JMS Serializer

You can use the rebuy Serializer stand alone. If you are already working with
JMS Serializer, you can also use the drop-in replacement for JMS serializer
[rebuy/serializer-jms-adapter](https://github.com/rebuy-oss/serializer-jms-adapter).
The drop-in adapter implements the JMS interfaces and provides fallback to the
regular JMS serializer for missing generated files and on other errors.

## Help

If you need help, please open an issue on github.

- [Contributing](CONTRIBUTING.md)
- [Upgrading](UPGRADING.md)
- [Changelog](CHANGELOG.md)
