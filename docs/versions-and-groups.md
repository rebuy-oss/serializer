# Versions and groups

Versions and groups apply only to the serializer. The deserializer methods do not take them.

The library generates one serializer for each group combination and each version:

- `versions`: a list of versions. `''` means "without version". Default: `['']`.
- `groupCombinations`: a list of `GroupCombination` objects. `new GroupCombination([])` means "without groups", and
  `new GroupCombination(['api', 'detail'])` means the groups `api` and `detail` together.
  Default: `[new GroupCombination([])]`.

Neither list may be empty.

To use different versions for one group combination, pass `versions` to the `GroupCombination`. These versions replace
the versions of the class. The library does not merge them.

```php
->withClass(
    Product::class,
    versions: ['1', '2'],
    groupCombinations: [
        new GroupCombination([]),
        new GroupCombination(['api', 'detail'], versions: ['2']),
    ],
)
```

This generates three serializers for `Product`:

| Groups          | Version |
|-----------------|---------|
| none            | 1       |
| none            | 2       |
| `api`, `detail` | 2       |
