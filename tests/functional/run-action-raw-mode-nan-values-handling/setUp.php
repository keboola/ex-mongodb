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
        sprintf("%s/../../datasets/%s", __DIR__, 'dataset-invalid-json-values.json'),
    ]);

    $process->mustRun();
};
