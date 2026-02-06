<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Keboola\Component\UserException;
use MongoExtractor\RelativeDateParser;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class RelativeDateParserTest extends TestCase
{
    private DateTimeImmutable $fixedNow;
    private RelativeDateParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixed date: 2026-01-21T10:00:00+00:00
        $this->fixedNow = new DateTimeImmutable('2026-01-21T10:00:00+00:00', new DateTimeZone('UTC'));
        $this->parser = new RelativeDateParser($this->fixedNow);
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public function parseDataProvider(): Generator
    {
        // {{now}} - current datetime
        yield 'now' => [
            '{"createdAt": {"$gte": "{{now}}"}}',
            '{"createdAt": {"$gte": {"$date": "2026-01-21T10:00:00+00:00"}}}',
        ];

        // {{NOW}} - case insensitive
        yield 'now uppercase' => [
            '{"createdAt": {"$gte": "{{NOW}}"}}',
            '{"createdAt": {"$gte": {"$date": "2026-01-21T10:00:00+00:00"}}}',
        ];

        // {{now-7d}} - 7 days ago
        yield '7 days ago' => [
            '{"createdAt": {"$gte": "{{now-7d}}"}}',
            '{"createdAt": {"$gte": {"$date": "2026-01-14T10:00:00+00:00"}}}',
        ];

        // {{now-2w}} - 2 weeks ago (14 days)
        yield '2 weeks ago' => [
            '{"createdAt": {"$gte": "{{now-2w}}"}}',
            '{"createdAt": {"$gte": {"$date": "2026-01-07T10:00:00+00:00"}}}',
        ];

        // {{now-1m}} - 1 month ago
        yield '1 month ago' => [
            '{"createdAt": {"$gte": "{{now-1m}}"}}',
            '{"createdAt": {"$gte": {"$date": "2025-12-21T10:00:00+00:00"}}}',
        ];

        // {{now-1y}} - 1 year ago
        yield '1 year ago' => [
            '{"createdAt": {"$gte": "{{now-1y}}"}}',
            '{"createdAt": {"$gte": {"$date": "2025-01-21T10:00:00+00:00"}}}',
        ];

        // Multiple placeholders in one query
        yield 'multiple placeholders' => [
            '{"createdAt": {"$gte": "{{now-30d}}", "$lte": "{{now}}"}}',
            '{"createdAt": {"$gte": {"$date": "2025-12-22T10:00:00+00:00"}, ' .
                '"$lte": {"$date": "2026-01-21T10:00:00+00:00"}}}',
        ];

        // No placeholders - query passes through unchanged
        yield 'no placeholders' => [
            '{"createdAt": {"$gte": {"$date": "2025-01-01T00:00:00Z"}}}',
            '{"createdAt": {"$gte": {"$date": "2025-01-01T00:00:00Z"}}}',
        ];

        // {{now-365d}} - 365 days ago (2025 is not a leap year, so 365 days before 2026-01-21 is 2025-01-21)
        yield '365 days ago' => [
            '{"createdAt": {"$gte": "{{now-365d}}"}}',
            '{"createdAt": {"$gte": {"$date": "2025-01-21T10:00:00+00:00"}}}',
        ];

        // Query with other conditions
        yield 'with other query conditions' => [
            '{"status": "active", "createdAt": {"$gte": "{{now-7d}}"}, "type": "order"}',
            '{"status": "active", "createdAt": {"$gte": {"$date": "2026-01-14T10:00:00+00:00"}}, "type": "order"}',
        ];
    }

    /**
     * @dataProvider parseDataProvider
     */
    public function testParse(string $input, string $expected): void
    {
        Assert::assertSame($expected, $this->parser->parse($input));
    }

    /**
     * @return Generator<string, array{string, bool}>
     */
    public function hasPlaceholdersDataProvider(): Generator
    {
        yield 'has placeholder' => [
            '{"createdAt": {"$gte": "{{now-7d}}"}}',
            true,
        ];

        yield 'no placeholder' => [
            '{"createdAt": {"$gte": {"$date": "2025-01-01T00:00:00Z"}}}',
            false,
        ];
    }

    /**
     * @dataProvider hasPlaceholdersDataProvider
     */
    public function testHasPlaceholders(string $query, bool $expected): void
    {
        Assert::assertSame($expected, $this->parser->hasPlaceholders($query));
    }

    public function testParseEmptyPlaceholderThrowsException(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Invalid relative date placeholder:');

        $this->parser->parse('{"createdAt": {"$gte": "{{}}"}}');
    }

    public function testDefaultNowUsesCurrentTime(): void
    {
        $parser = new RelativeDateParser();
        $result = $parser->parse('{"createdAt": {"$gte": "{{now}}"}}');

        Assert::assertStringContainsString('"$date":', $result);
        Assert::assertStringNotContainsString('{{now}}', $result);
    }
}
