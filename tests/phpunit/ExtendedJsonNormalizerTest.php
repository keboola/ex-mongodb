<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use Generator;
use MongoExtractor\DateNormalizer;
use MongoExtractor\ExtendedJsonNormalizer;
use MongoExtractor\NormalizerChain;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ExtendedJsonNormalizerTest extends TestCase
{
    /**
     * @dataProvider getDocumentsProvider
     */
    public function testNormalize(string $inputJson, string $expectedJson): void
    {
        $data = [json_decode($inputJson)];

        (new ExtendedJsonNormalizer())->normalize($data);

        Assert::assertSame($expectedJson, json_encode($data[0]));
    }

    /**
     * Decimal128 exists to hold values a float cannot represent, so the unwrapped value must stay
     * a string - a float cast would round the digits away and drop the trailing zeros.
     */
    public function testPrecisionIsPreserved(): void
    {
        $data = [json_decode('{"amount":{"$numberDecimal":"123456789012345678901234.5678"}}')];

        (new ExtendedJsonNormalizer())->normalize($data);

        $amount = self::propertiesOf($data[0])['amount'];
        Assert::assertIsString($amount);
        Assert::assertSame('123456789012345678901234.5678', $amount);
    }

    /**
     * The customer-reported case (CFTL-372): the same field is a plain number in some documents
     * and a Decimal128 in others, so no single mapping key could match both shapes before.
     */
    public function testMixedTypesInOneBatchBecomeUniform(): void
    {
        $data = [
            json_decode('{"amount":{"$numberDecimal":"150.00"}}'),
            json_decode('{"amount":150.0}'),
            json_decode('{"amount":null}'),
        ];

        (new ExtendedJsonNormalizer())->normalize($data);

        foreach ($data as $document) {
            $amount = self::propertiesOf($document)['amount'];
            Assert::assertTrue(
                is_scalar($amount) || is_null($amount),
                'Every document must expose a scalar, otherwise csvmap cannot write the column.',
            );
        }
    }

    /**
     * Guards the ordering contract with DateNormalizer: relaxed mode writes dates outside its
     * range as {"$date":{"$numberLong":"..."}}, and DateNormalizer needs that wrapper intact to
     * read the value as epoch milliseconds.
     */
    public function testNumberLongInsideDateIsLeftIntact(): void
    {
        $input = '{"birthDate":{"$date":{"$numberLong":"-1980449884000"}}}';
        $data = [json_decode($input)];

        (new ExtendedJsonNormalizer())->normalize($data);

        Assert::assertSame($input, json_encode($data[0]));
    }

    /**
     * @requires extension mongodb
     */
    public function testChainNormalizesDecimalWithoutBreakingDates(): void
    {
        $data = [json_decode('{"birthDate":{"$date":{"$numberLong":"-1980449884000"}},'
            . '"amount":{"$numberDecimal":"150.00"}}')];

        $chain = new NormalizerChain([
            new DateNormalizer(['birthDate.$date' => [
                'type' => 'date',
                'mapping' => ['destination' => 'timeColumn'],
            ]]),
            new ExtendedJsonNormalizer(),
        ]);
        $chain->normalize($data);

        $properties = self::propertiesOf($data[0]);
        Assert::assertSame('{"$date":"1907-03-31T03:01:56+00:00"}', json_encode($properties['birthDate']));
        Assert::assertSame('150.00', $properties['amount']);
    }

    /**
     * @param array<string, mixed>|object $document
     * @return array<string, mixed>
     */
    private static function propertiesOf(array|object $document): array
    {
        return get_object_vars((object) $document);
    }

    public function getDocumentsProvider(): Generator
    {
        yield 'top level decimal' => [
            'inputJson' => '{"amount":{"$numberDecimal":"150.00"}}',
            'expectedJson' => '{"amount":"150.00"}',
        ];

        yield 'nested decimal' => [
            'inputJson' => '{"nested":{"deeper":{"amount":{"$numberDecimal":"1.5"}}}}',
            'expectedJson' => '{"nested":{"deeper":{"amount":"1.5"}}}',
        ];

        yield 'decimal inside array' => [
            'inputJson' => '{"amounts":[{"$numberDecimal":"1.5"},2,{"$numberDecimal":"3.50"}]}',
            'expectedJson' => '{"amounts":["1.5",2,"3.50"]}',
        ];

        yield 'decimal inside array of subdocuments' => [
            'inputJson' => '{"items":[{"price":{"$numberDecimal":"9.99"}},{"price":1}]}',
            'expectedJson' => '{"items":[{"price":"9.99"},{"price":1}]}',
        ];

        yield 'decimal as _id' => [
            'inputJson' => '{"_id":{"$numberDecimal":"42"}}',
            'expectedJson' => '{"_id":"42"}',
        ];

        yield 'non finite double' => [
            'inputJson' => '{"ratio":{"$numberDouble":"NaN"}}',
            'expectedJson' => '{"ratio":"NaN"}',
        ];

        yield 'object id is left intact' => [
            'inputJson' => '{"_id":{"$oid":"5716054bee6e764c94fa7ddd"}}',
            'expectedJson' => '{"_id":{"$oid":"5716054bee6e764c94fa7ddd"}}',
        ];

        yield 'date is left intact' => [
            'inputJson' => '{"date":{"$date":"2020-05-18T16:00:00Z"}}',
            'expectedJson' => '{"date":{"$date":"2020-05-18T16:00:00Z"}}',
        ];

        yield 'binary is left intact' => [
            'inputJson' => '{"binary":{"$binary":{"base64":"AAAA","subType":"00"}}}',
            'expectedJson' => '{"binary":{"$binary":{"base64":"AAAA","subType":"00"}}}',
        ];

        yield 'timestamp is left intact' => [
            'inputJson' => '{"ts":{"$timestamp":{"t":1565545664,"i":1}}}',
            'expectedJson' => '{"ts":{"$timestamp":{"t":1565545664,"i":1}}}',
        ];

        yield 'user data with a numberDecimal key alongside other fields is left intact' => [
            'inputJson' => '{"weird":{"$numberDecimal":"1.5","note":"user data"}}',
            'expectedJson' => '{"weird":{"$numberDecimal":"1.5","note":"user data"}}',
        ];

        yield 'wrapper around a non scalar is left intact' => [
            'inputJson' => '{"weird":{"$numberDecimal":{"nested":"1.5"}}}',
            'expectedJson' => '{"weird":{"$numberDecimal":{"nested":"1.5"}}}',
        ];

        yield 'plain values are untouched' => [
            'inputJson' => '{"int":1,"float":1.5,"string":"x","bool":true,"null":null,"arr":[1,2]}',
            'expectedJson' => '{"int":1,"float":1.5,"string":"x","bool":true,"null":null,"arr":[1,2]}',
        ];
    }
}
