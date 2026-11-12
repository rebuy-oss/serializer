<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Class_\ObjectTypedPropertyFromJMSSerializerAttributeTypeRector;

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
    ->withSkip([
        ClosureToArrowFunctionRector::class,
        ObjectTypedPropertyFromJMSSerializerAttributeTypeRector::class,
    ])
    ->withPhpSets()
    ->withSets([SetList::TYPE_DECLARATION])
    ->withImportNames(importShortClasses: false)
    ->withCache('.cache/rector')
;
