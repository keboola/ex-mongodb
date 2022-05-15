<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

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

    public function testConvertObjectIdToString(): void
    {
        $input = '{ "id": 1, "_id": {"$oid": "61f8e6b99e2986a522ebb90f"}, "string":  "testString"}';
        $expected = '{ "id": 1, "_id": "ObjectId(\"61f8e6b99e2986a522ebb90f\")", "string":  "testString"}';

        Assert::assertSame(
            $expected,
            ExportHelper::convertObjectIdToString($input)
        );
    }

    public function testConvertStringIdToObjectId(): void
    {
        $input = '{"_id": ObjectId("5716054bee6e764c94fa7ddd")}';
        $expected = '{"_id": {"$oid": "5716054bee6e764c94fa7ddd"}}';

        Assert::assertSame(
            $expected,
            ExportHelper::convertStringIdToObjectId($input)
        );
    }

    public function testFixObjectIdInGteQuery(): void
    {
        $input = '{"$gte":"ObjectId(\"61f8e6b99e2986a522ebb90f\")"}';
        $expected = '{"$gte":ObjectId("61f8e6b99e2986a522ebb90f")}';

        Assert::assertSame(
            $expected,
            ExportHelper::fixObjectIdInGteQuery($input)
        );
    }

    public function getConvertDatesDataProvider(): array
    {
        // Note: Unreal cases are also tested to make it clear that REGEXP is working properly.
        return [
            'no-prefix' => [
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z"}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": "2020-05-18T16:00:00Z", "string":  "testString"}',
            ],
            'prefix' =>[
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z"}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": "ISODate(\"2020-05-18T16:00:00Z\")", "string":  "testString"}',
            ],
            'escaping' =>[
                '{ "id" : 1, "date": {"$date": "2020-05-\"\"\"18T16:00:00Z"}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": "2020-05-\"\"\"18T16:00:00Z", "string":  "testString"}',
            ],
            'escaping-prefix' =>[
                '{ "id" : 1, "date": {"$date": "2020-05-\"\"\"18T16:00:00Z"}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": "ISODate(\"2020-05-\\\\\"\\\\\"\\\\\"18T16:00:00Z\")", "string":  "testString"}',
            ],
            'no-spaces' => [
                '{"id":1,"date":{"$date": "2020-05-18T16:00:00Z"},"string":"testString"}',
                false,
                '{"id":1,"date":"2020-05-18T16:00:00Z","string":"testString"}',
            ],
            'no-spaces-prefix' =>[
                '{"id":1,"date":{"$date":"2020-05-18T16:00:00Z"},"string":"testString"}',
                true,
                '{"id":1,"date":"ISODate(\"2020-05-18T16:00:00Z\")","string":"testString"}',
            ],
            'empty' => [
                '{ "id" : 1, "date": {"$date": ""}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": "", "string":  "testString"}',
            ],
            'empty-prefix' =>[
                '{ "id" : 1, "date": {"$date": ""}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": "ISODate(\"\")", "string":  "testString"}',
            ],
            'invalid-1' => [
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
            ],
            'invalid-1-prefix' =>[
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": {"$date": "2020-05-18T16:00:00Z}, "string":  "testString"}',
            ],
            'invalid-2' => [
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
                false,
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
            ],
            'invalid-2-prefix' =>[
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
                true,
                '{ "id" : 1, "date": {"$date": 1234, "string":  "testString"}',
            ],
        ];
    }

    public function getFixIsoDateDataProvider(): array
    {
        // Note: Unreal cases are also tested to make it clear that REGEXP is working properly.
        return [
            'simple' => [
                '{"$gte":"ISODate(\"2020-05-18T16:00:00Z\")"}',
                '{"$gte":ISODate("2020-05-18T16:00:00Z")}',
            ],
            'empty-1' => [
                '{"$gte":"ISODate(\"\")"}',
                '{"$gte":ISODate("")}',
            ],
            'escaping' => [
                '{"$gte":"ISODate(\"2020-05-\\\\\"18\\\\\"T16:00:00Z\")"}',
                '{"$gte":ISODate("2020-05-\"18\"T16:00:00Z")}',
            ],
            'invalid-1' => [
                '{"$gte":"ISODate(\"2020-05\"\"-18T16:00:00Z\")"}',
                '{"$gte":ISODate("2020-05""-18T16:00:00Z")}',
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
            "{\"borough\": \"Bronx\",\"cuisine\": \"Bakery\", \"address.zipcode\": \"10452\"}",
            ExportHelper::addQuotesToJsonKeys(
                "{borough : \"Bronx\", cuisine: \"Bakery\", \"address.zipcode\": \"10452\"}"
            )
        );

        $this->assertSame(
            "{\"date\":{\"\$gte\":ISODate(\"2020-05-18T16:00:00Z\")}}",
            ExportHelper::addQuotesToJsonKeys(
                "{\"date\":{\"\$gte\":ISODate(\"2020-05-18T16:00:00Z\")}}"
            )
        );
    }
}
