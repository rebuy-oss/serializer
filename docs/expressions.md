# Expressions

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
