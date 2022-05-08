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
        'invalidJSON',
        '--legacy',
        '--drop',
        '--file',
        __DIR__ . '/source/data/in/dataset-invalid-json-values.json',
    ]);

    $process->mustRun();
};
