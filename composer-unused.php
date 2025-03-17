<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use Webmozart\Glob\Glob;

return static function (Configuration $config): Configuration {
    $config
        ->setAdditionalFilesFor('__root__', [
            __FILE__,
            ...Glob::glob(__DIR__ . '/config/*.php'),
        ]);

    return $config;
};
