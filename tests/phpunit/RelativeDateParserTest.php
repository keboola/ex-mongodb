<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
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
        $this->fixedNow = new DateTimeImmutable('2026-01-21T10:00:00+00:00', new DateTimeZone('UTC'));
        $this->parser = new RelativeDateParser($this->fixedNow);
    }

    public function testParseNow(): void
    {
        $input = '{"createdAt": {"$gte": "{{now}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2026-01-21T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseNowCaseInsensitive(): void
    {
        $input = '{"createdAt": {"$gte": "{{NOW}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2026-01-21T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseDaysAgo(): void
    {
        $input = '{"createdAt": {"$gte": "{{now-7d}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2026-01-14T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseWeeksAgo(): void
    {
        $input = '{"createdAt": {"$gte": "{{now-2w}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2026-01-07T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseMonthsAgo(): void
    {
        $input = '{"createdAt": {"$gte": "{{now-1m}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2025-12-21T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseYearsAgo(): void
    {
        $input = '{"createdAt": {"$gte": "{{now-1y}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2025-01-21T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseMultiplePlaceholders(): void
    {
        $input = '{"createdAt": {"$gte": "{{now-30d}}", "$lte": "{{now}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2025-12-22T10:00:00+00:00"}, ' .
            '"$lte": {"$date": "2026-01-21T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseNoPlaceholders(): void
    {
        $input = '{"createdAt": {"$gte": {"$date": "2025-01-01T00:00:00Z"}}}';

        Assert::assertSame($input, $this->parser->parse($input));
    }

    public function testHasPlaceholdersTrue(): void
    {
        Assert::assertTrue($this->parser->hasPlaceholders('{"createdAt": {"$gte": "{{now-7d}}"}}'));
    }

    public function testHasPlaceholdersFalse(): void
    {
        $query = '{"createdAt": {"$gte": {"$date": "2025-01-01T00:00:00Z"}}}';
        Assert::assertFalse($this->parser->hasPlaceholders($query));
    }

    public function testParseLargeNumbers(): void
    {
        $input = '{"createdAt": {"$gte": "{{now-365d}}"}}';
        $expected = '{"createdAt": {"$gte": {"$date": "2025-01-21T10:00:00+00:00"}}}';

        Assert::assertSame($expected, $this->parser->parse($input));
    }

    public function testParseWithOtherQueryConditions(): void
    {
        $input = '{"status": "active", "createdAt": {"$gte": "{{now-7d}}"}, "type": "order"}';
        $expected = '{"status": "active", "createdAt": {"$gte": {"$date": "2026-01-14T10:00:00+00:00"}}, ' .
            '"type": "order"}';

        Assert::assertSame($expected, $this->parser->parse($input));
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
