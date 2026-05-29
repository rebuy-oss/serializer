# Benchmarks

Repeatable [phpbench](https://phpbench.readthedocs.io) benchmarks for the runtime serialize/deserialize 
hot path. They generate the serializer/deserializer code for a large `ListModel` tree
(wide lists, hashmaps and collections of nested objects).

## Running

```bash
composer bench
```

To compare against a stored reference:

```bash
# store a labelled run
vendor/bin/phpbench run --progress=none --store --tag=baseline

# later, compare the working tree against it
vendor/bin/phpbench run --report=aggregate --ref=baseline
```
