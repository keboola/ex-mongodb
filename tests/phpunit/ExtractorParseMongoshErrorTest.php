<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use MongoExtractor\Extractor;
use PHPUnit\Framework\TestCase;

class ExtractorParseMongoshErrorTest extends TestCase
{
    public function testDnsResolutionErrorNotFound(): void
    {
        $stderr = "MongoServerSelectionError: getaddrinfo ENOTFOUND locahost\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame("Could not resolve hostname 'locahost'. Please check the host configuration.", $result);
    }

    public function testDnsResolutionErrorTemporaryFailure(): void
    {
        $stderr = "MongoServerSelectionError: getaddrinfo EAI_AGAIN locahost\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame("Could not resolve hostname 'locahost'. Please check the host configuration.", $result);
    }

    public function testAuthenticationError(): void
    {
        $stderr = "MongoServerError: Authentication failed.\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('Authentication failed.', $result);
    }

    public function testConnectionRefusedError(): void
    {
        $stderr = "MongoNetworkError: connect ECONNREFUSED 127.0.0.1:27017\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame(
            'Connection refused to 127.0.0.1:27017. Please check the host and port configuration.',
            $result,
        );
    }

    public function testConnectionTimeoutError(): void
    {
        $stderr = "MongoServerSelectionError: Server selection timed out\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('Connection timed out. Please check the host and port configuration.', $result);
    }

    public function testUnescapedCharactersError(): void
    {
        $stderr = "Password contains unescaped characters\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('Failed to parse connection URI. Please check the connection parameters.', $result);
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
        self::assertSame(
            'Connection refused to 127.0.0.1:27017. Please check the host and port configuration.',
            $result,
        );
    }

    public function testNonMongoErrorReturnsFirstLine(): void
    {
        $stderr = "Unexpected error occurred\nMore details\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('Unexpected error occurred', $result);
    }

    public function testUnmappedErrorPassedThrough(): void
    {
        $stderr = "MongoServerError: some unknown error\n";
        $result = Extractor::parseMongoshError($stderr, '');
        self::assertSame('some unknown error', $result);
    }
}
