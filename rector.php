<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Pest\Rector\Set\PestSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withParallel()
    ->withImportNames(
        importDocBlockNames: false,
        removeUnusedImports: true,
    )
    ->withPhpSets(
        php84: true,
    )
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        instanceOf: true,
        earlyReturn: true,
        naming: true,
    )
    ->withFluentCallNewLine();
