<?php

declare(strict_types = 1);

use MongoExtractor\FunctionalTests\DatadirTest;
use MongoExtractor\Tests\Traits\ImportDatasetTrait;
use Symfony\Component\Filesystem\Filesystem;

return static function (DatadirTest $test): void {
    $fs = new Filesystem;
    $datasetFilepath = sprintf('%s/../../datasets/big-dataset.json', __DIR__);
    $expectedCsvFilepath = sprintf('%s/expected/data/out/tables/big-data.csv', __DIR__);
    $fs->remove($datasetFilepath);
    $fs->remove($expectedCsvFilepath);

    $fs->dumpFile($datasetFilepath, "[\n");
    $fs->dumpFile($expectedCsvFilepath, "\"id\",\"name\",\"email\",\"phone\"\r\n");

    $numOfRows = 100000; // 100K
    for ($i = 0; $i < $numOfRows; $i++) {
        $items = [
            "id" => $i + 1,
            "name" => md5((string) rand()),
            "email" => md5((string) rand()),
            "phone" => md5((string) rand()),
        ];
        $json = json_encode($items, JSON_PRETTY_PRINT);
        $fs->appendToFile($datasetFilepath, $json . ($i < $numOfRows - 1 ? ",\n" : "\n]"));
        $fs->appendToFile($expectedCsvFilepath, '"' . implode('","', $items) . "\"\r\n");
    }

    (new class {
        use ImportDatasetTrait;
    })::importDatatasetNoAuthDb('big-data', 'big-dataset.json');
};
