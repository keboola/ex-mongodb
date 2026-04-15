<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use MongoExtractor\Extractor;
use PHPUnit\Framework\TestCase;

class ExtractorParseMongoshErrorTest extends TestCase
{
    public function testServerSelectionError(): void
    {
        $stderr = "MongoServerSelectionError: getaddrinfo ENOTFOUND locahost\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('getaddrinfo ENOTFOUND locahost', $result);
    }

    public function testAuthenticationError(): void
    {
        $stderr = "MongoServerError: Authentication failed.\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('Authentication failed.', $result);
    }

    public function testFallbackToStdoutWhenStderrEmpty(): void
    {
        $stdout = "some error output\n";
        $result = Extractor::parseMongoshError('', $stdout);
        self::assertSame('some error output', $result);
    }

    public function testEmptyOutputReturnsDefault(): void
    {
        $result = Extractor::parseMongoshError('', '');
        self::assertSame('Connection test failed', $result);
    }

    public function testMultiLineStderrExtractsMongoError(): void
    {
        $stderr = "Some warning line\nMongoNetworkError: connect ECONNREFUSED 127.0.0.1:27017\nstack trace...\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('connect ECONNREFUSED 127.0.0.1:27017', $result);
    }

    public function testNonMongoErrorReturnFirstLine(): void
    {
        $stderr = "Unexpected error occurred\nMore details\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('Unexpected error occurred', $result);
    }
}
