<?php

declare(strict_types=1);

use MongoExtractor\FunctionalTests\DatadirTest;
use MongoExtractor\Tests\Traits\ImportDatasetTrait;

return static function (DatadirTest $test): void {
    putenv('SSL_CA=' . file_get_contents('/tmp/ca-cert.pem'));
    putenv('SSL_CERT=' . file_get_contents('/tmp/client-cert.pem'));
    putenv('SSL_KEY=' . file_get_contents('/tmp/client-key.pem'));

    (new class { use ImportDatasetTrait;

    })::importDatatasetCluster('restaurants', 'dataset.json');
};
