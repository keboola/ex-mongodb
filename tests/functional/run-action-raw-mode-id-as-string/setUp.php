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
        'restaurantsIdAsString',
        '--drop',
        '--jsonArray',
        '--file',
        __DIR__ . '/source/data/in/dataset-id-as-string.json',
    ]);

    $process->mustRun();
};
