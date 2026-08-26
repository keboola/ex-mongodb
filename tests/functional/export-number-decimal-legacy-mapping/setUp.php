<?php

declare(strict_types=1);

use MongoExtractor\FunctionalTests\DatadirTest;
use MongoExtractor\Tests\Traits\ImportDatasetTrait;

/**
 * Backward-compatibility guard for the legacy suffixed mapping key ("amount.$numberDecimal").
 *
 * Documents 1 and 3 of the dataset are the actual guard: they are Decimal128, and the expected
 * CSV holds exactly what the suffixed key produced before Extended JSON unwrapping existed.
 *
 * Document 2 is deliberately NOT preserved behaviour. It holds a plain double, where the suffixed
 * key used to resolve to nothing and wrote an empty cell; it now writes the value. That is an
 * intentional improvement, not a regression - do not "restore" the empty cell if a diff against
 * an older revision makes it look like one.
 *
 * The shared dataset is the point: this test and export-number-decimal map the same documents
 * through the two different key styles and must produce byte-identical output.
 */
return static function (DatadirTest $test): void {
    (new class { use ImportDatasetTrait;

    })::importDatatasetNoAuthDb('number-decimal', 'dataset-number-decimal.json');
};
