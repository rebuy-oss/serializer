<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withRules(
        [
            InlineConstructorDefaultToPropertyRector::class,
        ]
    )
    ->withPhpSets()
    ->withSets([SetList::TYPE_DECLARATION])
    ->withImportNames(importShortClasses: false)
    ->withCache('.cache/rector'.FileCacheStorage::class)
;
