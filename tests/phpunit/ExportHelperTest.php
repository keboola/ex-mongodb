<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use Generator;
use MongoExtractor\ExportHelper;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ExportHelperTest extends TestCase
{
    /**
     * @dataProvider getConvertDatesDataProvider
     */
    public function testConvertDatesToString(string $input, bool $isoDatePrefix, string $expectedOutput): void
    {
        Assert::assertSame($expectedOutput, ExportHelper::convertDatesToString($input, $isoDatePrefix));
    }

    /**
     * @dataProvider getFixIsoDateDataProvider
     */
    public function testFixIsoDateInGteQuery(string $input, string $expectedOutput): void
    {
        Assert::assertSame($expectedOutput, ExportHelper::fixIsoDateInGteQuery($input));
    }

    /**
     * @dataProvider getMappingsProvider
     * @param array<mixed, mixed> $input
     * @param array<mixed, mixed> $expected
     */
    public function testRemoveTypeFromMappingKeys(array $input, array $expected): void
    {
        ExportHelper::removeTypesInMappingKeys($input);
        Assert::assertSame($expected, $input);
    }

    public function testConvertObjectIdToString(): void
    {
        $input = '{ "id": 1, "_id": {"$oid": "61f8e6b99e2986a522ebb90f"}, "string":  "testString"}';
        $expected = '{ "id": 1, "_id": "ObjectId(\"61f8e6b99e2986a522ebb90f\")", "string":  "testString"}';

        Assert::assertSame(
            $expected,
            ExportHelper::convertObjectIdToString($input),
        );
    }

    public function testConvertDecimalToString(): void
    {
        $input = '{ "id": 1, "amount": {"$numberDecimal": "150.00"}, "string":  "testString"}';
        $expected = '{ "id": 1, "amount": "NumberDecimal(\\"150.00\\")", "string":  "testString"}';

        Assert::assertSame(
            $expected,
            ExportHelper::convertDecimalToString($input),
        );
    }

    /**
     * The state value has to survive the round trip back into a query as a Decimal128, not as a
     * string: BSON type ordering sorts every number before every string, so a string "$gte" would
     * silently match no documents at all.
     */
    public function testFixDecimalInGteQuery(): void
    {
        $input = '{"amount":{"$gte":"NumberDecimal(\\"783.028\\")"}}';
        $expected = '{"amount":{"$gte":{"$numberDecimal": "783.028"}}}';

        Assert::assertSame(
            $expected,
            ExportHelper::fixDecimalInGteQuery($input),
        );
    }

    public function testDecimalMarkerRoundTrip(): void
    {
        $exportOutput = '{"amount":{"$numberDecimal":"123456789012345678901234.5678"}}';

        $state = json_decode(ExportHelper::convertSpecialColumnsToString($exportOutput), true);
        Assert::assertIsArray($state);

        $query = ExportHelper::fixSpecialColumnsInGteQuery(
            (string) json_encode(['amount' => ['$gte' => $state['amount']]]),
        );

        Assert::assertSame('{"amount":{"$gte":{"$numberDecimal": "123456789012345678901234.5678"}}}', $query);
    }

    public function testConvertStringIdToObjectId(): void
    {
        $input = '{"_id": ObjectId("5716054bee6e764c94fa7ddd")}';
        $expected = '{"_id": {"$oid": "5716054bee6e764c94fa7ddd"}}';

        Assert::assertSame(
            $expected,
            ExportHelper::convertStringIdToObjectId($input),
        );
    }

    public function testFixObjectIdInGteQuery(): void
    {
        $input = '{"$gte":"ObjectId(\"61f8e6b99e2986a522ebb90f\")"}';
        $expected = '{"$gte":{"$oid": "61f8e6b99e2986a522ebb90f"}}';

        Assert::assertSame(
            $expected,
            ExportHelper::fixObjectIdInGteQuery($input),
        );
    }

    /**
     * @return array<string,array<int,string|boolean>>
     */
    public function getConvertDatesDataProvider(): array
    {
        // Note: Unreal cases are also tested to make it clear that REGEXP is working properly.
        return [
            'no-prefix' => [
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z"}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": "2020-05-18T16:00:00Z", "string":  "testString"}',
            ],
            'prefix' => [
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z"}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": "ISODate(\"2020-05-18T16:00:00Z\")", "string":  "testString"}',
            ],
            'escaping' => [
                '{ "id" : 1, "date": {"$date": "2020-05-\"\"\"18T16:00:00Z"}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": "2020-05-\"\"\"18T16:00:00Z", "string":  "testString"}',
            ],
            'escaping-prefix' => [
                '{ "id" : 1, "date": {"$date": "2020-05-\"\"\"18T16:00:00Z"}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": "ISODate(\"2020-05-\\\\\"\\\\\"\\\\\"18T16:00:00Z\")", "string":  "testString"}',
            ],
            'no-spaces' => [
                '{"id":1,"date":{"$date": "2020-05-18T16:00:00Z"},"string":"testString"}',
                false,
                '{"id":1,"date":"2020-05-18T16:00:00Z","string":"testString"}',
            ],
            'no-spaces-prefix' => [
                '{"id":1,"date":{"$date":"2020-05-18T16:00:00Z"},"string":"testString"}',
                true,
                '{"id":1,"date":"ISODate(\"2020-05-18T16:00:00Z\")","string":"testString"}',
            ],
            'empty' => [
                '{ "id" : 1, "date": {"$date": ""}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": "", "string":  "testString"}',
            ],
            'empty-prefix' => [
                '{ "id" : 1, "date": {"$date": ""}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": "ISODate(\"\")", "string":  "testString"}',
            ],
            'invalid-1' => [
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
            ],
            'invalid-1-prefix' => [
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
            ],
            'invalid-2' => [
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
            ],
            'invalid-2-prefix' => [
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
            ],
        ];
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function getFixIsoDateDataProvider(): array
    {
        // Note: Unreal cases are also tested to make it clear that REGEXP is working properly.
        return [
            'simple' => [
                '{"$gte":"ISODate(\"2020-05-18T16:00:00Z\")"}',
                '{"$gte":{"$date": "2020-05-18T16:00:00Z"}}',
            ],
            'empty-1' => [
                '{"$gte":"ISODate(\"\")"}',
                '{"$gte":{"$date": ""}}',
            ],
            'escaping' => [
                '{"$gte":"ISODate(\"2020-05-\\\\\"18\\\\\"T16:00:00Z\")"}',
                '{"$gte":{"$date": "2020-05-\"18\"T16:00:00Z"}}',
            ],
            'invalid-1' => [
                '{"$gte":"ISODate(\"2020-05\"\"-18T16:00:00Z\")"}',
                '{"$gte":{"$date": "2020-05""-18T16:00:00Z"}}',
            ],
            'invalid-2' => [
                '{"$gte":"ISODate()"}',
                '{"$gte":"ISODate()"}',
            ],
            'invalid-3' => [
                '{"$gte":"abc"}',
                '{"$gte":"abc"}',
            ],
            'invalid-4' => [
                '{"$gte":1234}',
                '{"$gte":1234}',
            ],
        ];
    }

    public function testAddQuotesToJsonKeys(): void
    {
        $this->assertSame(
            '{"borough": "Bronx","cuisine": "Bakery", "address.zipcode": "10452"}',
            ExportHelper::addQuotesToJsonKeys(
                '{borough : "Bronx", cuisine: "Bakery", "address.zipcode": "10452"}',
            ),
        );

        $this->assertSame(
            '{"date":{"$gte":ISODate("2020-05-18T16:00:00Z")}}',
            ExportHelper::addQuotesToJsonKeys(
                '{"date":{"$gte":ISODate("2020-05-18T16:00:00Z")}}',
            ),
        );
    }

    public function getMappingsProvider(): Generator
    {
        yield [
            'inputJson' => json_decode('
            {
      "_id.$oid": {
        "type": "column",
        "mapping": {
          "destination": "id"
        }
      },
      "numberLong.$numberLong": "numberLong",
      "numberLongInObject.$numberLong": "numberLongInObject"
    }', true),
            'expected' => [
                '_id.$oid' => [
                    'type' => 'column',
                    'mapping' => [
                        'destination' => 'id',
                    ],
                ],
                'numberLong' => 'numberLong',
                'numberLongInObject' => 'numberLongInObject',
            ],
        ];

        yield [
            'inputJson' => json_decode('
            {
      "_id.$oid": {
        "type": "column",
        "mapping": {
          "destination": "id"
        }
      },
      "numberLong": "numberLong",
      "date.$date": "date"
    }', true),
            'expected' => [
                '_id.$oid' => [
                    'type' => 'column',
                    'mapping' => [
                        'destination' => 'id',
                    ],
                ],
                'numberLong' => 'numberLong',
                'date.$date' => 'date',
            ],
        ];

        yield [
            'inputJson' => json_decode('
            {
      "_id.$oid": {
        "type": "column",
        "mapping": {
          "destination": "id"
        }
      },
      "numberLong.$numberLong": "numberLong",
      "numberInt.$numberInt": "numberInt",
      "numberDouble.$numberDouble": "numberDouble",
      "date.$date": "date",
      "numberDecimal.$numberDecimal": "numberDecimal",
      "binary.$binary.base64": "binary"
    }', true),
            'expected' => [
                '_id.$oid' => [
                    'type' => 'column',
                    'mapping' => [
                        'destination' => 'id',
                    ],
                ],
                'numberLong' => 'numberLong',
                'numberInt' => 'numberInt',
                'numberDouble' => 'numberDouble',
                'date.$date' => 'date',
                'numberDecimal' => 'numberDecimal',
                'binary.$binary.base64' => 'binary',
            ],
        ];
    }
}
