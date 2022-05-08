<?php

declare(strict_types = 1);

use MongoExtractor\FunctionalTests\DatadirTest;
use Symfony\Component\Process\Process;

return static function (DatadirTest $test): void {
    $process = new Process([
        'mongoimport',
        '--host',
        'mongodb',
        '--db',
        'test',
        '--collection',
        'sameSubDocs',
        '--drop',
        '--jsonArray',
        '--file',
        __DIR__ . '/source/data/in/dataset-same-subdocs.json',
    ]);

    $process->mustRun();
};
