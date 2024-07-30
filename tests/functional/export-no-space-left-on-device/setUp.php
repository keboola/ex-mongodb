<?php

declare(strict_types=1);

use MongoExtractor\FunctionalTests\DatadirTest;
use MongoExtractor\Tests\Traits\ImportDatasetTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function simulateFullDisk(string $tmpFolder): void
{
    $tablesFolderPath = sprintf('%s/out/tables', $tmpFolder);
    (new Filesystem())->mkdir($tablesFolderPath);
    $process = Process::fromShellCommandline(sprintf('ln -s /dev/full %s/export-one.csv', $tablesFolderPath));

    $process->mustRun();
}

return static function (DatadirTest $test): void {
    simulateFullDisk($test->getTemp()->getTmpFolder());

    (new class { use ImportDatasetTrait;

    })::importDatatasetNoAuthDb('restaurants', 'dataset.json');
};
