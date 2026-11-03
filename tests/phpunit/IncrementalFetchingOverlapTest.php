<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use Generator;
use Keboola\Component\UserException;
use MongoExtractor\Config\ExportOptions;
use MongoExtractor\Export;
use PHPUnit\Framework\TestCase;

class IncrementalFetchingOverlapTest extends TestCase
{
    /**
     * @dataProvider overlapProvider
     */
    public function testBuildIncrementalFetchingParams(
        ?int $overlap,
        string|int|float|null $inputState,
        string $expectedQuery,
    ): void {
        $exportOptions = Export::buildIncrementalFetchingParams($this->createExportOptions($overlap), $inputState);

        self::assertSame($expectedQuery, $exportOptions->getQuery());
        self::assertSame('{"finishedAt":1}', $exportOptions->getSort());
    }

    /**
     * @return Generator<string, array{?int, string|int|float|null, string}>
     */
    public function overlapProvider(): Generator
    {
        yield 'overlap shifts the date back' => [
            300,
            'ISODate("2026-11-03T14:37:42.526Z")',
            '{"finishedAt":{"$gte":{"$date": "2026-11-03T14:32:42.526Z"}}}',
        ];

        yield 'overlap crosses midnight' => [
            300,
            'ISODate("2026-11-03T00:02:00.000Z")',
            '{"finishedAt":{"$gte":{"$date": "2026-11-02T23:57:00.000Z"}}}',
        ];

        yield 'date without milliseconds' => [
            60,
            'ISODate("2026-11-03T14:37:42Z")',
            '{"finishedAt":{"$gte":{"$date": "2026-11-03T14:36:42.000Z"}}}',
        ];

        yield 'date with offset is converted to UTC' => [
            60,
            'ISODate("2026-11-03T15:37:42.526+01:00")',
            '{"finishedAt":{"$gte":{"$date": "2026-11-03T14:36:42.526Z"}}}',
        ];

        yield 'zero overlap keeps the date' => [
            0,
            'ISODate("2026-11-03T14:37:42.526Z")',
            '{"finishedAt":{"$gte":{"$date": "2026-11-03T14:37:42.526Z"}}}',
        ];

        yield 'no overlap keeps the stored value as is' => [
            null,
            'ISODate("2026-11-03T14:37:42.526Z")',
            '{"finishedAt":{"$gte":{"$date": "2026-11-03T14:37:42.526Z"}}}',
        ];

        yield 'no overlap with numeric column' => [
            null,
            123,
            '{"finishedAt":{"$gte":123}}',
        ];

        yield 'first run without state ignores overlap' => [
            300,
            null,
            '{}',
        ];
    }

    /**
     * @dataProvider unsupportedStateProvider
     */
    public function testOverlapOnNonDateColumnFails(string|int|float $inputState): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('"incrementalFetchingOverlap" is supported only for date incremental columns.');

        Export::buildIncrementalFetchingParams($this->createExportOptions(300), $inputState);
    }

    /**
     * @return Generator<string, array{string|int|float}>
     */
    public function unsupportedStateProvider(): Generator
    {
        yield 'integer' => [123];
        yield 'float' => [12.5];
        yield 'string' => ['abc'];
        yield 'object id' => ['ObjectId("5716054bee6e764c94fa7ddd")'];
        yield 'decimal' => ['NumberDecimal("783.028")'];
    }

    public function testOverlapFromStringConfigValue(): void
    {
        $exportOptions = new ExportOptions([
            'tableName' => 'runs',
            'collection' => 'runs',
            'mode' => ExportOptions::MODE_RAW,
            'incremental' => true,
            'incrementalFetchingColumn' => 'finishedAt',
            'incrementalFetchingOverlap' => '300',
        ]);

        self::assertSame(300, $exportOptions->getIncrementalFetchingOverlap());
    }

    private function createExportOptions(?int $overlap): ExportOptions
    {
        $options = [
            'tableName' => 'runs',
            'collection' => 'runs',
            'mode' => ExportOptions::MODE_RAW,
            'incremental' => true,
            'incrementalFetchingColumn' => 'finishedAt',
        ];
        if ($overlap !== null) {
            $options['incrementalFetchingOverlap'] = $overlap;
        }

        return new ExportOptions($options);
    }
}
