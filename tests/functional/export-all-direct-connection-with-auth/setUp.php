<?php

declare(strict_types = 1);

use MongoExtractor\FunctionalTests\DatadirTest;
use Symfony\Component\Process\Process;

return static function (DatadirTest $test): void {
    $process = new Process([
        'mongoimport',
        '--host',
        'mongodb-auth',
        '--username',
        'user',
        '--password',
        'p#a!s@sw:o&r%^d',
        '--db',
        'test',
        '--collection',
        'restaurants',
        '--drop',
        '--jsonArray',
        '--file',
        __DIR__ . '/source/data/in/dataset.json',
    ]);

    $process->mustRun();
};
