<?php

declare(strict_types = 1);

use MongoExtractor\FunctionalTests\DatadirTest;
use MongoExtractor\Tests\Traits\ImportDatasetTrait;

return static function (DatadirTest $test): void {
    (new class { use ImportDatasetTrait; })::importDatatasetCluster('restaurants', 'dataset.json');
};